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

define('views/email-template/record/panels/information', ['views/record/panels/side'], function (Dep) {

    return Dep.extend({

        templateContent: '{{{infoText}}}',

        data: function () {
            const list2 = this.getMetadata().get(['clientDefs', 'EmailTemplate', 'placeholderList']) || [];

            const defs = this.getMetadata().get('app.emailTemplate.placeholders') || {};

            const list1 = Object.keys(defs)
                .sort((a, b) => {
                    const o1 = defs[a].order || 0;
                    const o2 = defs[b].order || 0;

                    return o1 - o2;
                });

            const placeholderList = [...list1, ...list2];

            if (!placeholderList.length) {
                return {
                    infoText: ''
                };
            }

            const $header = $('<h4>').text(this.translate('Available placeholders', 'labels', 'EmailTemplate') + ':');

            const $liList = placeholderList.map(item => {
                return $('<li>').append(
                    $('<code>').text('{' + item + '}'),
                    ' &#8211; ',
                    $('<span>').text(this.translate(item, 'placeholderTexts', 'EmailTemplate'))
                )
            });

            const $ul = $('<ul>').append($liList);

            const $text = $('<span>')
                .addClass('complex-text')
                .append($header, $ul);

            return {
                infoText: $text[0].outerHTML,
            };
        },
    });
});
