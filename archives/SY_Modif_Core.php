<?php
/**********************************************************************************************************************
Modif_Core - 105

Permet de modifier certains fichiers du core pour remédier à des problèmes divers.
	ATTENTION les chaine '$' doivent être remplacé par '"  . "$" . "' pour ne pas être interprété par PHP
	Pour mettre une ligne en rem, mettre $txtNew à ''.
**********************************************************************************************************************/

$repOrg = '/var/www/html/mg/modif_core';

// Copy du thème custom
$repDest = '/var/www/html/core/themes';
shell_exec("sudo chmod -R 777 $repDest");
shell_exec("sudo cp -r $repOrg/core2019_Dark_MG $repDest/");

// Copy map geoloc
$repDest = '/var/www/html/plugins/geoloc/core/template/dashboard';
shell_exec("sudo chmod -R 777 $repDest");
shell_exec("sudo cp -r $repOrg/geoloc.html $repDest/geoloc.html");

// ***************************************************** CORRECTIONS SOURCES *****************************************/
// Correction de MTDOWLING suite appel incorrect provoquant des erreurs de log en série
$file = "/var/www/html/vendor/mtdowling/cron-expression/src/Cron/MinutesField.php";
$txtOrg = "$" . "minutes[" . "$" . "position]);";
$txtNew = "intval(" . "$" . "minutes[" . "$" . "position]+0));";
sed($txtOrg, $txtNew, $file);

// Suppression des recalculs forcés des min/max dans CMD.CLASS
$file = "/var/www/html/core/class/cmd.class.php";

// '#maxValue#' => $this->getConfiguration('maxValue'),
$txtOrg = "'#minValue#' => "  . "$" . "this->getConfiguration('minValue', 0),";
$txtNew = "'#minValue#' => "  . "$" . "this->getConfiguration('minValue'),";
sed($txtOrg, $txtNew, $file);

$txtOrg = "'#maxValue#' => "  . "$" . "this->getConfiguration('maxValue', 100),";
$txtNew = "'#maxValue#' => "  . "$" . "this->getConfiguration('maxValue'),";
sed($txtOrg, $txtNew, $file);

// ****************************************************** FONCTION SED ********************************************
function sed($txtOrg, $txtNew, $file, $rem=1) {
	shell_exec("sudo chmod -R 777 $file");
	
	// ********************* POUR DEBUG ***********************
	//$file = "/var/www/html/mg/util/tmp.php"; 
	// ********************* POUR DEBUG ***********************

	$requete = "@$txtOrg@$txtNew" . ($rem ? "// ***** Modifié par MG@" : "@");
	
	$requete = str_replace('$', '\$', $requete);
	$requete = str_replace('"', '\"', $requete);
	$requete = str_replace('[', '\[', $requete);
	$requete = str_replace(']', '\]', $requete);

	$requete = "sudo sed -i -e \"s$requete"."g\"  $file";
	$ret = shell_exec($requete);
	mg::Message('', "----------------------------$requete");
}
/*

<?php
******************************************* FICHIER TMP.PHP POUR TEST *************************************************

// geotrav.class.php -----------------------------------
	return $jsondata['results'][0]['elevation'];

// Activités autonomes. -------------------------------------
            $date->setTime($date->format('H'), $minutes[$position]);

// cmd.class.php -------------------------------------
'#minValue#' => $this->getConfiguration('minValue', 0),

'#maxValue#' => $this->getConfiguration('maxValue', 100),

?>		
		
*/				
// ****************************************************************************************************************

?>