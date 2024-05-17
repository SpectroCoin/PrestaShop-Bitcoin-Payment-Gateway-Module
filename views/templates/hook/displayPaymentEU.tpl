<section>
  <div class="modal fade" id="spectrocoin-modal" tabindex="-1" role="dialog" aria-labelledby="SpectroCoin information" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          <h2>Pay with Crypto via SpectroCoin</h2>
        </div>
        <div class="modal-body">
          <p class="payment_module">
            <a href="{$link->getModuleLink('spectrocoin', 'payment')|escape:'html'}" title="{l s='Cryptocurrency provided by SpectroCoin' mod='spectrocoin'}">
              <img src="{$this_path_bw}spectrocoin-logo.svg" alt="{l s='Crypto provided by SpectroCoin' mod='spectrocoin'}" width="129" height="49"/>
              {l s='Crypto provided by SpectroCoin' mod='spectrocoin'}
            </a>
          </p>
          <div class="alert alert-info">
            <p>
                <strong>Having trouble?</strong> Contact <a href="mailto:spectrocoin@merchant.com">mailto:spectrocoin@merchant.com</a>.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
