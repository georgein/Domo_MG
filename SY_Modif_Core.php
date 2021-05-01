<?php
/************************************************************************************************************************
SY_Modif_Core - 181

Permet de remplacer certains fichiers de Jeedom posant problème après la mise à jour.
**********************************************************************************************************************/

$logTimeLine = mg::getParam('Log', 'timeLine');

$repOrg = '/var/www/html/mg/modif_php_jeedom';

// MODALE VARIABLES
$fileName = 'dataStore.management.php';
$repDest = ' /var/www/html/desktop/modal';
copyFile ($repDest, $repOrg, $fileName);

// ERREURS LOG CMD.CLASS
$fileName = 'cmd.class.php';
$repDest = '/var/www/html/core/class';
copyFile ($repDest, $repOrg, $fileName);

// ERREURS LOGS ASUSWRT
$fileName = 'asuswrt.class.php';
$repDest = '/var/www/html/plugins/asuswrt/core/class';
copyFile ($repDest, $repOrg, $fileName);

// ERREURS LOG METEO-FRANCE
$fileName = 'meteofrance.class.php';
$repDest = '/var/www/html/plugins/meteofrance/core/class';
copyFile ($repDest, $repOrg, $fileName);

mg::message($logTimeLine, "CORE : MàJ des PHP.");

// *************************************************************************
// *************************************************************************
// *************************************************************************
function copyFile ($repDest, $repOrg, $fileName) {
  mg::messageT('', "Remplacement du fichier $fileName du repertoire $repDest.");
  shell_exec("sudo chmod -R 777 $repDest");
  shell_exec("sudo cp -r $repOrg/$fileName $repDest/");
  }
  
?>