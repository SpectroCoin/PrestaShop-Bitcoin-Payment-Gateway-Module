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
    <!-- Left column: Configuration form -->
    <div class="flex-col-1 flex-col">
        <div>
            <h4><b>{$configurationTitle|escape:'html':'UTF-8'}</b></h4>
        </div>
        <div class="form">
            {*
              Use nofilter so the PrestaShop HelperForm HTML is rendered properly
              (instead of showing literal <form> tags).
            *}
            {$form nofilter}
        </div>
    </div>

    <!-- Right column: Intro, steps, contact info -->
    <div class="flex-col-2 flex-col">
        <div class="logo-container">
            <a href="https://spectrocoin.com/" target="_blank">
                <img class="logo" src="{$logoPath|escape:'html':'UTF-8'}" alt="SpectroCoin Logo">
            </a>
        </div>

        <h4>{$introductionTitle|escape:'html':'UTF-8'}</h4>
        <p>{$introductionText|escape:'html':'UTF-8'}</p>

        <ol>
            {*
              Each step may contain HTML (links), so we use nofilter
              to ensure <a href="..."> is rendered properly.
            *}
            {foreach from=$tutorialSteps item=step}
                <li>{$step nofilter}</li>
            {/foreach}
        </ol>

        <p>
            <strong>{l s='Note:' mod='spectrocoin'}</strong>
            {$note|escape:'html':'UTF-8'}
        </p>

        <div class="contact-information">
            {*
              contactInformation may contain <br> or <a> tags,
              so we use nofilter to render them.
            *}
            {$contactInformation nofilter}
        </div>
    </div>
</div>
