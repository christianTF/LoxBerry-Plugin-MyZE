<?php

require_once "loxberry_web.php";
require_once "defines.php";

$L = LBSystem::readlanguage("language.ini");
$template_title = "Renault My Z.E. Plugin";
$helplink = "https://www.loxwiki.eu/x/KoNdAw";
$helptemplate = "help.html";

LBWeb::lbheader($template_title, $helplink, $helptemplate);

?>

<style>
.mono {
	font-family:monospace;
	font-size:110%;
	font-weight:bold;
	color:green;

}
#overlay 
{
  display: none !important;
}
</style>

<!-- Form SETTINGS -->
<form id="form" onsubmit="return false;">

<!-- My ZE Online -->

<div class="wide">My Z.E. Online</div>

<div data-role="fieldcontain">
	<label for="user">Username</label>
	<input name="user" id="user" type="text" />
	<p class="hint">This is the username of your <i>My ZE Online</i> account.</p>
</div>

<div data-role="fieldcontain">
	<label for="pass">Password</label>
	<input name="pass" id="pass" type="password">
	<!--<p class="hint">This is the username of your <i>My ZE Online</i> account.</p>-->
</div>


<div class="wide">Plugin data transmission</div>
<p><i>You should use only one data transmission option: MQTT or HTTP. Your Miniserver is an old gentleman.</i></p>

<!-- MQTT --> 

<fieldset data-role="controlgroup">
	<input type="checkbox" name="MQTT.enabled" id="MQTT.enabled" class="refreshdisplay">
	<label for="MQTT.enabled">Enable to use MQTT for data transfer</label>
	<p class="hint">If you locally have the MQTT Gateway plugin installed, leave Broker host and credentials empty. The Z.E. plugin then automatically collects your settings from the MQTT Gateway plugin (not shown in this form). </p>
</fieldset>

<div data-role="fieldcontain" style="display:none" class="mqtthidden">
	<label for="MQTT.topic">Base topic</label>
	<input name="MQTT.topic" id="MQTT.topic" type="text">
	<p class="hint">This is the base topic, the plugin publishes it's data to. Subscribe for <span class="mono">basetopic/#</span>. Default (if empty) is <span class="mono">renault-ze</span>.</p>
</div>

<div data-role="fieldcontain" style="display:none" class="mqtthidden">
	<label for="MQTT.host">Broker Hostname:Port</label>
	<input name="MQTT.host" id="MQTT.host" type="text">
	<p class="hint">Example: mybroker:1883. Leave this empty, if your settings from the MQTT Gateway plugin should be used.</p>
</div>

<div data-role="fieldcontain" style="display:none" class="mqtthidden">
	<label for="MQTT.user">Broker Username</label>
	<input name="MQTT.user" id="MQTT.user" type="text">
	<p class="hint">This is the username of your <i>MQTT broker</i>. Leave this empty, if your settings from the MQTT Gateway plugin should be used, or you have enabled anonymous access.</p>
</div>

<div data-role="fieldcontain" style="display:none" class="mqtthidden">
	<label for="MQTT.pass">Broker Password</label>
	<input name="MQTT.pass" id="MQTT.pass" type="password">
	<!--<p class="hint">This is the username of your <i>My ZE Online</i> account.</p>-->
</div>


<!-- Loxone HTTP --> 

<fieldset data-role="controlgroup">
	<input type="checkbox" name="Loxone.enabled" id="Loxone.enabled" class="refreshdisplay">
	<label for="Loxone.enabled">Enable to use HTTP transfer to Miniserver VI's/VTI's</label>
	<p class="hint">This option enables direct pushes to virtual inputs on the Miniserver. VIs need to be named exactly as shown below.</p>
</fieldset>

<div style="display:none" class="loxonehidden">
<?php

echo LBWeb::mslist_select_html( [ LABEL => 'Miniserver to send', FORMID => 'Loxone.msnr', DATA_MINI => '0' ] );

?>
</div>

</form>
<!-- End of form -->
<hr>

<div style="display:flex;align-items:center;justify-content:center;height:16px;min-height:16px">
	<span id="savemessages"></span>
</div>
<div style="display:flex;align-items:center;justify-content:center;">
	<button class="ui-btn ui-btn-icon-right" id="saveapply" data-inline="true">Save and Apply</button>
</div>

<div id="jsonconfig" style="display:none">
<?php
$configjson = file_get_contents(CONFIGFILE);
if( !empty($configjson) or !empty( json_decode($configjson) ) ) {
	echo $configjson;
} else {
	echo "{}";
}
?>
</div>

<?php
LBWeb::lbfooter();
?>

<script>

var config;

$( document ).ready(function() {

	config = JSON.parse( $("#jsonconfig").text() );
	formFill();
	viewhide();
	
	
	$(".refreshdisplay").click(function(){ viewhide(); });
	$("#saveapply").click(function(){ saveapply(); });
	$("#saveapply").blur(function(){ 
		$("#savemessages").html("");
	});
	

});


function viewhide()
{
	if( $("#MQTT\\.enabled").is(":checked") ) {
		$(".mqtthidden").fadeIn();
	} else {
		$(".mqtthidden").fadeOut();
	}
	
	if( $("#Loxone\\.enabled").is(":checked") ) {
		$(".loxonehidden").fadeIn();
	} else {
		$(".loxonehidden").fadeOut();
	}
}

function formFill()
{
	if (typeof user !== 'undefined') $("#user").val( config.user );
	if (typeof pass !== 'undefined') $("#pass").val( config.pass );
	
	if( typeof config.MQTT !== 'undefined') {
		if (typeof config.MQTT.enabled !== 'undefined') $("#MQTT\\.enabled").prop('checked', config.MQTT.enabled).checkboxradio('refresh');
		if (typeof config.MQTT.topic !== 'undefined') $("#MQTT\\.topic").val( config.MQTT.topic );
		if (typeof config.MQTT.host !== 'undefined') $("#MQTT\\.host").val( config.MQTT.host );
		if (typeof config.MQTT.user !== 'undefined') $("#MQTT\\.user").val( config.MQTT.user );
		if (typeof config.MQTT.pass !== 'undefined') $("#MQTT\\.pass").val( config.MQTT.pass );
	}
	
	if( typeof config.Loxone !== 'undefined') {
		if (typeof config.Loxone.enabled !== 'undefined') $("#Loxone\\.enabled").prop('checked', config.Loxone.enabled).checkboxradio('refresh');
		if (typeof config.Loxone.msnr !== 'undefined') $("#Loxone\\.msnr").val( config.Loxone.msnr ).selectmenu("refresh", true);
	}
}

function saveapply() 
{
	$("#savemessages").html("Submitting...");
	$("#savemessages").css("color", "grey");
	
	$.post( "ajax-handler.php?action=saveconfig", $( "#form" ).serialize() )
	.done(function( data ) {
		console.log("Done:", data);
		$("#savemessages").html("Saved successfully");
		$("#savemessages").css("color", "green");
		
		config = data;
		formFill();
	})
	.fail(function( error, textStatus, errorThrown ) {
		console.log("Fail:", error, textStatus, errorThrown);
		$("#savemessages").html("Error "+error.status+": "+error.responseJSON.error);
		$("#savemessages").css("color", "red");
		
	});
}



</script>





