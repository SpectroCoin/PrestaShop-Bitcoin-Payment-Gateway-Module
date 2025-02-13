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
            <h4><b>{$configurationTitle|escape:'html':'UTF-8'}</b></h4>
        </div>
        <div class="form">
            {$form|escape:'html':'UTF-8'}
            {$style|escape:'html':'UTF-8'}
        </div>
    </div>
    <div class="flex-col-2 flex-col">
        <div class="logo-container">
            <a href="https://spectrocoin.com/" target="_blank">
                <img class="logo" src="{$logoPath|escape:'html':'UTF-8'}" alt="SpectroCoin Logo">
            </a>
        </div>
        <h4>{$introductionTitle|escape:'html':'UTF-8'}</h4>
        <p>{$introductionText|escape:'html':'UTF-8'}</p>
        <ol>
            {foreach from=$tutorialSteps item=step}
                <li>{$step|escape:'html':'UTF-8'}</li>
            {/foreach}
        </ol>
        <p><strong>{l s='Note:' mod='spectrocoin'}</strong> {$note|escape:'html':'UTF-8'}</p>
        <div class="contact-information">
            {$contactInformation|escape:'html':'UTF-8'}
        </div>
    </div>
</div>
