<?php
/************************************************************************************************************************
SY_Modif_Core - 181

Permet de remplacer certains fichiers de Jeedom posant problème après la mise à jour.
**********************************************************************************************************************/

$logTimeLine = mg::getParam('Log', 'timeLine');

if (mg::declencheur('begin')) {
	// Sauvegarde TEMPLATE jMQTT
	$repDest = '/var/www/html/mg/modif_php_jeedom/template_jMQTT';
	$fileName = 'var/www/html/plugins/jMQTT/core/config/template/*.json';
	copyFile ($repDest, '', $fileName);
	mg::message($logTimeLine, "CORE : Sauvegarde des fichiers terminé.");

// Restaure
} elseif (mg::declencheur('end')) {
	$repOrg = '/var/www/html/mg/modif_php_jeedom';
	
	// Restaure TEMPLATE jMQTT
	$fileName = 'template_jMQTT/*.json';
	$repDest = '/var/www/html/plugins/jMQTT/core/config/template/';
	copyFile ($repDest, $repOrg, $fileName);

	// MODALE VARIABLES
	$fileName = 'dataStore.management.php';
	$repDest = ' /var/www/html/desktop/modal';
//	copyFile ($repDest, $repOrg, $fileName);

	// ERREURS LOG CMD.CLASS
	$fileName = 'cmd.class.php';
	$repDest = '/var/www/html/core/class';
//	copyFile ($repDest, $repOrg, $fileName);

	// ERREURS LOGS ASUSWRT
	$fileName = 'asuswrt.class.php';
	$repDest = '/var/www/html/plugins/asuswrt/core/class';
//	copyFile ($repDest, $repOrg, $fileName);

	// ERREURS LOG METEO-FRANCE
	$fileName = 'meteofrance.class.php';
	$repDest = '/var/www/html/plugins/meteofrance/core/class';
//	copyFile ($repDest, $repOrg, $fileName);

	mg::message($logTimeLine, "CORE : restauration des fichiers terminé.");
}

// *************************************************************************
// *************************************************************************
// *************************************************************************
function copyFile ($repDest, $repOrg, $fileName) {
	mg::messageT('', "Copie du/des fichiers $repOrg/$fileName VERS $repDest/.");
	shell_exec("sudo chmod -R 777 $repDest");
	shell_exec("sudo cp -r $repOrg/$fileName $repDest/");
  }
  
?>