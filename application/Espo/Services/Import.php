<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2023 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Services;

use Espo\Repositories\Import as Repository;
use Espo\Entities\Import as ImportEntity;

use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\ForbiddenSilent;
use Espo\Core\Exceptions\NotFoundSilent;
use Espo\Core\FieldProcessing\ListLoadProcessor;
use Espo\Core\Record\Collection as RecordCollection;
use Espo\Core\Select\SearchParams;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Tools\Export\Params as ExportParams;
use Espo\Tools\Export\Export as ExportTool;


/**
 * @extends Record<ImportEntity>
 */
class Import extends Record
{
    public function __construct(
        private ExportTool $exportTool
    ) {
        parent::__construct();
    }

    /**
     * @param non-empty-string $link
     * @throws NotFoundSilent If a record not found.
     * @throws Forbidden If no access.
     */
    public function findLinked(string $id, string $link, SearchParams $searchParams): RecordCollection
    {
        if (!in_array($link, ['imported', 'duplicates', 'updated'])) {
            return parent::findLinked($id, $link, $searchParams);
        }

        /** @var ?ImportEntity $entity */
        $entity = $this->getImportRepository()->getById($id);

        if (!$entity) {
            throw new NotFoundSilent();
        }

        $foreignEntityType = $entity->get('entityType');

        if (!$this->acl->check($entity, Table::ACTION_READ)) {
            throw new Forbidden();
        }

        if (!$this->acl->check($foreignEntityType, Table::ACTION_READ)) {
            throw new Forbidden();
        }

        $query = $this->selectBuilderFactory
            ->create()
            ->from($foreignEntityType)
            ->withStrictAccessControl()
            ->withSearchParams($searchParams)
            ->build();

        /** @var \Espo\ORM\Collection<\Espo\ORM\Entity> $collection */
        $collection = $this->getImportRepository()->findResultRecords($entity, $link, $query);

        $listLoadProcessor = $this->injectableFactory->create(ListLoadProcessor::class);

        $recordService = $this->recordServiceContainer->get($foreignEntityType);

        foreach ($collection as $e) {
            $listLoadProcessor->process($e);
            $recordService->prepareEntityForOutput($e);
        }

        $total = $this->getImportRepository()->countResultRecords($entity, $link, $query);

        return new RecordCollection($collection, $total);
    }

    private function getImportRepository(): Repository
    {
        /** @var Repository */
        return $this->getRepository();
    }

    /**
     * @param non-empty-string $link
     * @return RecordCollection<Entity>
     */
    public function getLinkedRecords(string $importId, string $link): RecordCollection
    {
        $searchParams = SearchParams::create()
            ->withOrderBy('createdAt')
            ->withOrder(SearchParams::ORDER_ASC);

        $linkedRecords = $this->findLinked(
            $importId,
            $link,
            $searchParams);

        return $linkedRecords;
    }

    /**
     * @param RecordCollection<Entity> $records
     */
    public function exportRecords(RecordCollection $records): ?string
    {
        if ($this->acl->getPermissionLevel('exportPermission') !== Table::LEVEL_YES) {
            throw new ForbiddenSilent("User has no 'export' permission.");
        }

        if ($records->getTotal() === 0) {
            return null;
        }

        if (!$records->getCollection() instanceof EntityCollection ||
            !$records->getCollection()->getEntityType()) {
            return null;
        }

        $exportEntityType = $records->getCollection()->getEntityType();

        $exportParams = ExportParams::create($exportEntityType)
            ->withFormat('csv')
            ->withAccessControl();

        $attachment_id = $this->exportTool
                              ->setParams($exportParams)
                              ->setCollection($records->getCollection())
                              ->run()
                              ->getAttachmentId();

        return $attachment_id;
    }
}
