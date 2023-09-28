<?php

//reposnseErrorCode.php

function renderResponseErrorCode($errorCode, $errorMessage)
{
    ?>
    <div class = "container">
  <div class = "content_container">
    <div class = "header_container">
      <h3>Error</h3>
    </div>
    <div class = "content_content">
      <div class = "form_body">
        
        <div class = "form_content response_code">
          <span class = "form-header">Response code:</span>
          <span class = "form-text"><?php $errorCode . " " . $errorMessage?> </span>
        </div>
        
        <div class = "form_content possible_cause">
          <span class = "form-header">Possible causes:</span>
          <span class = "form-text"></span>
        </div>
        
        <div class = "form_content general_cause">
          <span class = "form-header">General causes:</span>
          <span class = "form-text"></span>
        </div>
        
        <button>Return to shop</button>
      </div>
    </div>
    <div class = "footer_container">
      <div class = "footer_link">
        <a>Contact support</a>
      </div>
    </div>
  </div>
</div>
<?php
}