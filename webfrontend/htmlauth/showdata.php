<?php

require_once "loxberry_web.php";
require_once "defines.php";

$navbar[1]['active'] = null;
$navbar[2]['active'] = True;


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


.table {
  width: 90%;
  margin: auto;
  table-layout: fixed;
  display: table;
  border-collapse: collapse;
  border: 1px solid grey;
  padding: 5px;
}

.table_row {
  display: table-row;
  border: 1px solid grey;
  padding: 5px;

}

.table_head {
	color: white;
	background-color: #6dac20;
	font-weight: bold;
	text-shadow: 1px 1px 2px black;
}

.table_col {
  display: table-cell;
  border: 1px solid grey;
  padding: 5px;
}

.table_col_value {
  width:20%;
}



</style>

<div class="wide">Query links and data</div>
<p>In all links, the combination <span class="mono">&lt;lbuser&gt;:&lt;lbpass&gt; </span>needs to be replaced with your <b>LoxBerry's</b> username and password.</p>
<div style="display:flex; align-items: center; justify-content: center;">
	<div style="flex: 0 0 95%;padding:5px" data-role="fieldcontain">
		<label for="summarylink">Query battery data for all vehicles</label>
		<input type="text" id="summarylink" name="summarylink" data-mini="true" value="<?=$lbzeurl."?action=summary";?>" readonly>
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
				</div> \
				';
				
				conditionLastLink = ' \
				<div style="display:flex; align-items: center; justify-content: center;"> \
					<div style="flex: 0 0 95%;padding:10px"  data-role="fieldcontain"> \
						<label>Last air-condition start time</label> \
						<input type="text" id="condlastlink_'+vehicle.VIN+'" name="condlastlink_'+vehicle.VIN+'" data-mini="true" value="'+lbzeurl+'?action=conditionlast&vehicle='+vehicle.VIN+'" readonly> \
					</div> \
				</div> \
				';
				
				conditionLink = ' \
				<div style="display:flex; align-items: center; justify-content: center;"> \
					<div style="flex: 0 0 95%;padding:10px" data-role="fieldcontain"> \
						<label>Enable air-condition</label> \
						<input type="text" id="condlink_'+vehicle.VIN+'" name="condlink_'+vehicle.VIN+'" data-mini="true" value="'+lbzeurl+'?action=condition&vehicle='+vehicle.VIN+'" readonly> \
					</div> \
				</div> \
				';
				
				chargeLink = ' \
				<div style="display:flex; align-items: center; justify-content: center;"> \
					<div style="flex: 0 0 95%;padding:10px" data-role="fieldcontain"> \
						<label for="chargelink_'+vehicle.VIN+'">Start charging</label> \
						<input type="text" id="chargelink_'+vehicle.VIN+'" name="chargelink_'+vehicle.VIN+'" data-mini="true" value="'+lbzeurl+'?action=charge&vehicle='+vehicle.VIN+'" readonly> \
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
	
	strHtml = '\
		<div class="table" role="table" id="datatable_'+vin+'" aria-label="Data">\
			<div class="table_row">\
				<div class="table_col table_head">HTTP Virtual Input / Virtual Text Input</div>\
				<div class="table_col table_head">MQTT Topic</div>\
				<div class="table_col table_head table_col_value">Value</div>\
			</div>\
		</div>\
	';
	vdiv.append(strHtml);
	
	
	
	// strHtml = '<table style="border:1;width:100%;margin:0 auto;"> \
		// <tr>\
			// <th>HTTP Virtual Input / Virtual Text Input</th>\
			// <th>MQTT Topic</th>\
			// <th>Value</td>\
		// </tr>';
	// vdiv.append(strHtml);
	strHtml = "";	
	
	$.each ( data, function( prop, val ) { 
				
				mqtt = prop.split(/_(.+)/);
				mqttprop = mqtttopic+'/'+mqtt[0]+'/'+mqtt[1];
				
				// strHtml+= '\
					// <tr>\
						// <td>'+prop+'</td>\
						// <td>'+mqttprop+'</td>\
						// <td>'+val+'</td>\
					// </tr>';
				
				strHtml+= '\
					<div class="table_row">\
						<div class="table_col ">'+prop+'</div>\
						<div class="table_col">'+mqttprop+'</div>\
						<div class="table_col table_col_value">'+val+'</div>\
					</div>\
				';
				
		});
	$('#datatable_'+vin).append(strHtml);
	queryCondition( vin );
	
		
	// } else {
		// $("#content").append("Sorry, no vehicles found :-(");
	// }	
}


function showCondition ( vin, data ) 
{
	
	vdivid = 'vehicle_'+vin;
	vdiv=$("#"+vdivid);
	strHtml = "";
	
	$.each ( data, function( prop, val ) { 
				
				mqtt = prop.split(/_(.+)/);
				mqttprop = mqtttopic+'/'+mqtt[0]+'/'+mqtt[1];
				// strHtml += '\
				// <tr>\
					// <td>'+prop+'</td>\
					// <td>'+mqttprop+'</td>\
					// <td>'+val+'</td>\
				// </tr>';
				strHtml+= '\
					<div class="table_row">\
						<div class="table_col">'+prop+'</div>\
						<div class="table_col">'+mqttprop+'</div>\
						<div class="table_col table_col_value">'+val+'</div>\
					</div>\
				';
		});
	$('#datatable_'+vin).append(strHtml);
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





