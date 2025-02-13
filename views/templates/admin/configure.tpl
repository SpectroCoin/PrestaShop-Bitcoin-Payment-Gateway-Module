{**
 * SpectroCoin Module
 *
 * Copyright (C) 2014-2025 SpectroCoin
 *
 * This template is part of the SpectroCoin module.
 * It is distributed under the terms of the GNU General Public License,
 * either version 2 of the License or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see the GNU General Public License.
 *}

<div class="spectrocoin-settings flex-container">
    <div class="flex-col-1 flex-col">
        <div>
            <h4><b>{$configurationTitle}</b></h4>
        </div>
        <div class="form">
            {$form}
            {$style}
        </div>
    </div>
    <div class="flex-col-2 flex-col">
        <div class="logo-container">
            <a href="https://spectrocoin.com/" target="_blank">
                <img class="logo" src="{$logoPath}" alt="SpectroCoin Logo">
            </a>
        </div>
        <h4>{$introductionTitle}</h4>
        <p>{$introductionText}</p>
        <ol>
            {foreach from=$tutorialSteps item=step}
                <li>{$step}</li>
            {/foreach}
        </ol>
        <p><strong>{l s='Note:' mod='spectrocoin'}</strong> {$note}</p>
        <div class="contact-information">
            {$contactInformation nofilter}
        </div>
    </div>
</div>
