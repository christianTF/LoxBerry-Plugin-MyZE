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

<div class="wide">Available data</div>
<div id="content">
</div>



<hr>
<!--
<div style="display:flex;align-items:center;justify-content:center;height:16px;min-height:16px">
	<span id="savemessages"></span>
</div>
<div style="display:flex;align-items:center;justify-content:center;">
	<button class="ui-btn ui-btn-icon-right" id="saveapply" data-inline="true">Save and Apply</button>
</div>
-->


<?php
LBWeb::lbfooter();
?>

<script>

$( document ).ready(function() {

querySummary();

/*	$("#saveapply").click(function(){ saveapply(); });
	$("#saveapply").blur(function(){ 
		$("#savemessages").html("");
	});
*/

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
				vdiv.append("<h2>"+vehicle.VIN+"</h2>");
				vdiv.append(vehicle.user_id);
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
	
	vdiv.append('<table style="border:1">');
	
	$.each ( data, function( prop, val ) { 
				vdiv.append('<tr>');
				vdiv.append('<td>'+prop+'</td>');
				vdiv.append('<td>'+val+'</td>');
				vdiv.append('</tr>');
				// queryBattery(vehicle.VIN);
		});
	vdiv.append('</table>');
		
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

</script>





