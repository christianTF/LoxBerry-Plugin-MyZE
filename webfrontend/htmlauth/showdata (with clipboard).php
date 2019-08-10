<?php

require_once "loxberry_web.php";
require_once "defines.php";

$L = LBSystem::readlanguage("language.ini");
$template_title = "Renault My Z.E. Plugin";
$helplink = "https://www.loxwiki.eu/x/KoNdAw";
$helptemplate = "help.html";

LBWeb::lbheader($template_title, $helplink, $helptemplate);


// Load Config

if( ! file_exists(CONFIGFILE) ) {
	echo "<p>You first need to save the configuration.</p>";
	LBWeb::lbfooter();
	exit();
} else {
	$configdata = file_get_contents(CONFIGFILE);
	$config = json_decode($configdata);
	if( empty($config) ) {
		echo "<p>You first need to save the configuration.</p>";
		LBWeb::lbfooter();
		exit(1);
	}

	
}

// Init variables
$lbzeurl ="http://<lbuser>:<lbpass>@".lbhostname().":".lbwebserverport()."/admin/plugins/".LBPPLUGINDIR."/ze.php";
$mqtttopic = !empty($config->MQTT->topic) ? $config->MQTT->topic : "renault-ze";

?>

<style>
.mono {
	font-family:monospace;
	font-size:110%;
	font-weight:bold;
	color:green;

}

table {
border-collapse: collapse;	
}

table, th, td {
  border: 1px solid grey;
  padding: 5px;
  text-align: left;
}

#overlay 
{
  display: none !important;
}
</style>

<div class="wide">Query links and data</div>

<div style="display:flex; align-items: center; justify-content: center;">
	<div style="flex: 0 0 95%;padding:5px" data-role="fieldcontain">
		<label for="summarylink">Query battery data for all vehicles</label>
		<input type="text" id="summarylink" name="summarylink" data-mini="true" value="<?=$lbzeurl."?action=summary";?>" readonly>
	</div>
		<div style="flex: 1;padding:5px">
			<a href="#" class="ui-btn ui-icon-tag ui-btn-icon-notext ui-corner-all copytoclipboard" data-idtocopy="summarylink">Clipboard</a>
		</div>
	</div>
</div>
<hr>

<div id="content">
</div>
	
<hr>

<?php
LBWeb::lbfooter();
?>

<script>

lbzeurl = '<?=$lbzeurl;?>';
mqtttopic = '<?=$mqtttopic;?>';

$( document ).ready(function() {

querySummary();

/*	$("#saveapply").click(function(){ saveapply(); });
	$("#saveapply").blur(function(){ 
		$("#savemessages").html("");
	});
*/

	$(".copytoclipboard").on("click", function(){ 
		var idToCopy = $(this).data("idtocopy");
		console.log( "Element to copy:", idToCopy );
		if( idToCopy != undefined ) {
			//copyToClipboard( $("#idToCopy") )
			copyToClipboard( idToCopy )
		}
	});



});

function querySummary() 
{
	$("#content").html("<i>Querying data...This may take 10 or 20 seconds...</i>");
	$("#content").css("color", "grey");
	
	$.get( "ajax-handler.php?action=getsummary" )
	.done(function( data ) {
		console.log("Done:", data);
		showVehicles( data );
	})
	.fail(function( error, textStatus, errorThrown ) {
		console.log("Fail:", error, textStatus, errorThrown);
		if( typeof error.responseJSON !== 'undefined' ) 
			$("#content").html("Error "+error.status+": "+error.responseJSON.error);
		else
			$("#content").html("Error "+error.status+": "+error.statusText);
		$("#content").css("color", "red");
		
	});
}

function queryBattery( vin ) 
{
	$.get( "ajax-handler.php?action=getbattery&vin="+vin )
	.done(function( data ) {
		console.log("Battery Done:", data);
		showBattery( vin, data );
	})
	.fail(function( error, textStatus, errorThrown ) {
		console.log("Fail:", error, textStatus, errorThrown);
	});
}

function queryCondition( vin ) 
{
	$.get( "ajax-handler.php?action=getconditionlast&vin="+vin )
	.done(function( data ) {
		console.log("Condition Done:", data);
		showCondition( vin, data );
	})
	.fail(function( error, textStatus, errorThrown ) {
		console.log("Fail:", error, textStatus, errorThrown);
	});
}

function showVehicles ( data ) 
{
	$("#content").html("");
	$("#content").css("color", "black");
	
	vehicles = get(data, 'user.associated_vehicles');
	if ( vehicles ) {
		$.each ( vehicles, function( index, vehicle ) { 
				vdivid = 'vehicle_'+vehicle.VIN;
				$("#content").append('<div id="'+vdivid+'"></div>');
				vdiv=$("#"+vdivid);
				vdiv.append("<h2>Vehicle "+vehicle.VIN+' <span style="font-size:70%">User '+vehicle.user_id+'</span></h2>');
				
				// Generate URL to query battery
				batteryLink = '\
				<div style="display:flex; align-items: center; justify-content: center;"> \
					<div style="flex: 0 0 95%;padding:10px"  data-role="fieldcontain"> \
						<label>Query this battery</label> \
						<input type="text" id="batterylink_'+vehicle.VIN+'" name="batterylink_'+vehicle.VIN+'" data-mini="true" value="'+lbzeurl+'?action=battery&vehicle='+vehicle.VIN+'" readonly> \
					</div> \
						<div style="flex: 1;padding:5px"> \
							<a href="#" id="btnbatt_'+vehicle.VIN+'" class="ui-btn ui-icon-tag ui-btn-icon-notext ui-corner-all copytoclipboard" data-idtocopy="batterylink_'+vehicle.VIN+'">Clipboard</a> \
						</div> \
					</div> \
				</div> \
				';
				
				conditionLastLink = ' \
				<div style="display:flex; align-items: center; justify-content: center;"> \
					<div style="flex: 0 0 95%;padding:10px"  data-role="fieldcontain"> \
						<label>Last air-condition start time</label> \
						<input type="text" id="condlastlink_'+vehicle.VIN+'" name="condlastlink_'+vehicle.VIN+'" data-mini="true" value="'+lbzeurl+'?action=conditionlast&vehicle='+vehicle.VIN+'" readonly> \
					</div> \
						<div style="flex: 1;padding:5px"> \
							<a href="#" id="btncondlast_'+vehicle.VIN+'" class="ui-btn ui-icon-tag ui-btn-icon-notext ui-corner-all copytoclipboard" data-idtocopy="condlastlink_'+vehicle.VIN+'">Clipboard</a> \
						</div> \
					</div> \
				</div> \
				';
				
				conditionLink = ' \
				<div style="display:flex; align-items: center; justify-content: center;"> \
					<div style="flex: 0 0 95%;padding:10px" data-role="fieldcontain"> \
						<label>Enable air-condition</label> \
						<input type="text" id="condlink_'+vehicle.VIN+'" name="condlink_'+vehicle.VIN+'" data-mini="true" value="'+lbzeurl+'?action=condition&vehicle='+vehicle.VIN+'" readonly> \
					</div> \
						<div style="flex: 1;padding:5px"> \
							<a href="#" id="btncond_'+vehicle.VIN+'" class="ui-btn ui-icon-tag ui-btn-icon-notext ui-corner-all copytoclipboard" data-idtocopy="condlink_'+vehicle.VIN+'">Clipboard</a> \
						</div> \
					</div> \
				</div> \
				';
				
				chargeLink = ' \
				<div style="display:flex; align-items: center; justify-content: center;"> \
					<div style="flex: 0 0 95%;padding:10px" data-role="fieldcontain"> \
						<label for="chargelink_'+vehicle.VIN+'">Start charging</label> \
						<input type="text" id="chargelink_'+vehicle.VIN+'" name="chargelink_'+vehicle.VIN+'" data-mini="true" value="'+lbzeurl+'?action=charge&vehicle='+vehicle.VIN+'" readonly> \
					</div> \
						<div style="flex: 1;padding:5px"> \
							<a href="#" id="btncharge_'+vehicle.VIN+'" class="ui-btn ui-icon-tag ui-btn-icon-notext ui-corner-all copytoclipboard" data-idtocopy="chargelink_'+vehicle.VIN+'">Clipboard</a> \
						</div> \
					</div> \
				</div> \
				';
				
				vdiv.append(batteryLink+conditionLastLink+conditionLink+chargeLink).trigger("create");
				
				queryBattery(vehicle.VIN);
		});
		
	} else {
		$("#content").append("Sorry, no vehicles found :-(");
	}	
}

function showBattery ( vin, data ) 
{
	
	vdivid = 'vehicle_'+vin;
	vdiv=$("#"+vdivid);
	
	vdiv.append('<table style="border:1;width:95%">');

	vdiv.append('<tr>');
	vdiv.append('<th>HTTP Virtual Input / Virtual Text Input</th>');
	vdiv.append('<th>MQTT Topic</th>');
	vdiv.append('<th>Value</td>');
	vdiv.append('</tr>');

	
	$.each ( data, function( prop, val ) { 
				
				mqtt = prop.split(/_(.+)/);
				mqttprop = mqtttopic+'/'+mqtt[0]+'/'+mqtt[1];
				vdiv.append('<tr>');
				vdiv.append('<td>'+prop+'</td>');
				vdiv.append('<td>'+mqttprop+'</td>');
				vdiv.append('<td>'+val+'</td>');
				vdiv.append('</tr>');
		});
	queryCondition( vin );
	vdiv.append('</table>');
		
	// } else {
		// $("#content").append("Sorry, no vehicles found :-(");
	// }	
}


function showCondition ( vin, data ) 
{
	
	vdivid = 'vehicle_'+vin;
	vdiv=$("#"+vdivid);
	
	$.each ( data, function( prop, val ) { 
				
				mqtt = prop.split(/_(.+)/);
				mqttprop = mqtttopic+'/'+mqtt[0]+'/'+mqtt[1];
				vdiv.append('<tr>');
				vdiv.append('<td>'+prop+'</td>');
				vdiv.append('<td>'+mqttprop+'</td>');
				vdiv.append('<td>'+val+'</td>');
				vdiv.append('</tr>');
		});
		
	// } else {
		// $("#content").append("Sorry, no vehicles found :-(");
	// }	
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

// Returns a deep property or object, without checking everything
get = function(obj, key) {
    return key.split(".").reduce(function(o, x) {
        return (typeof o == "undefined" || o === null) ? o : o[x];
    }, obj);
}

// Copies data to clipboard
function copyToClipboard( element ) {
    $("#"+element).select();
	document.execCommand("copy");
}

</script>





