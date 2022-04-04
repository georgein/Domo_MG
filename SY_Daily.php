<?php
/**********************************************************************************************************************
Daily - 105


Si flaggé et lancé par EndBackup :
		Vérifie le BackupCloud du jour après 'end_backup'
		Lance le backup GDrive après 'end_backup'
		Effectue et nettoie les snapshots automatique de VmWare après 'end_backup'
		Effectue un contrôle de la sirène (si pas OK, alerte après 24 h via monitoring)
		Relance les scénarios du tableau (Variables générales, Têtes de scénario, ...).
		Réequerre les droits de /var/www/html/*.* à 777.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$equipBackupGDrive, $vmWareJeedom, $vmWareMQTT, $vmWareAntenne, $googleCastStart


//N° des scénarios à lancer à chaque éxécution:
	$tab_Scenario = array(
//							'137',						// Init Paramètrages
//							'127'						// EDF Conso
	);

//Variables :

//Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$soignerZwave = mg::getParam('System', 'soignerzwave');			// Soigne le réseau Zwave
	$equerreOS = mg::getParam('System', 'equerreOS');					// MàJ des droits, nettoie ancien log
	$relanceScenarios = mg::getParam('System', 'relanceScenarios');	// Lance les scénarios du tableau 'tab_Scenario'
	$savGdrive = mg::getParam('System', 'savGdrive');				// Lance les sauvegardes sur gdrive
	$ctrlBackup = mg::getParam('System', 'ctrlBackup');				// Vérifie le BackupCloud du jour
	$snapshotsVmWare = mg::getParam('System', 'snapShotsVmware');	// Lance la prise de snapshots sur VmWare.
	$snapshotspNbJours = mg::getParam('System', 'snapshotsNbJours');// Nb de jours de conservation des snapshot.

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
if ($equerreOS) {
	
	//=================================================================================================================
	mg::MessageT('', "! CREATION LOG DEFAUT : _Timeline, _Test");
	//=================================================================================================================
	mg::setValSql('config', 'core', 'log::level::_Timeline', 'value', '{"100":"0","200":"1","300":"0","400":"0","1000":"0","default":"0"}');
	mg::setValSql('config', 'core', 'log::level::_Test', 'value', '{"100":"0","200":"1","300":"0","400":"0","1000":"0","default":"0"}');
	
	//=================================================================================================================
	mg::MessageT('', "! MISE A JOUR DES DROITS / NETTOYAGE LOG / RELANCE WIFI JPI");
	//=================================================================================================================
	shell_exec("sudo chown -R www-data:www-data /var/www/html");
	shell_exec("sudo chmod -R 777 /var/www/html"); 
	mg::Message($logTimeLine, "Daily - Repose des droits et maintenance OS OK.");
			
	// Nettoyage divers des logs et gz
	// shell_exec('sudo find / -name "*.?.log" -exec rm {} \;'); // suppression des 1.log, 2.log ...
	// shell_exec('sudo find /var/log -name "*.gz" -exec rm {} \;'); // suppression des .gz du rep /var/log
}

if ($relanceScenarios) {
	//=================================================================================================================
	mg::MessageT('', "! LANCEMENT SCENARIOS MAINTENANCE");
	//=================================================================================================================
	for ($i = 0; $i < count($tab_Scenario); $i++) {
		mg::setScenario($tab_Scenario[$i], 'start');
		sleep(1);
		
		mg::setCmd($googleCastStart); // Relance GoogleCast ??????????????????
	}
}

if (mg::declencheur('schedule')) {
	//=================================================================================================================
	mg::MessageT('', "! TACHES JOURNALIERE VIA SCHEDULER");
	//=================================================================================================================
	// *EFFACEMENT DES LOG DU JOUR
//	log::removeAll();
	
/*	if ($soignerZwave) { 
		mg::zwaveSoins(); 
		mg::Message($logTimeLine, "Daily - Soigner Zwave OK.");
	}*/
	// ............................
}

if (mg::declencheur('end_backup')) {

	if ($ctrlBackup) {
		//=============================================================================================================
		mg::MessageT('', "! CONTROLE BACKUP JEEDOM");
		//=============================================================================================================
		$messageBackup = '';
		$tabBackup = jeedom::listBackup();
		foreach ($tabBackup as $Backup) {
			if (strpos($Backup, date('Y-m-d', time() - 60 * 60 * 24)) !== false || strpos($Backup, date('Y-m-d')) !== false) {
				$messageBackup = "Daily - Backup Jeedom OK.";
			  break;
			}
		}
		if ($messageBackup == '') {
			$messageBackup = "Daily - Backup Jeedom NON FAIT !!!";
			mg::Message('MESSAGE', $messageBackup);
		}
		mg::Message($logTimeLine, $messageBackup);
	}
	
	if ($snapshotsVmWare) {
		//=============================================================================================================
		mg::MessageT('', "! GESTION DES SNAPSHOTS");
		//=============================================================================================================
		SnapShot($vmWareJeedom, $snapshotspNbJours, $logTimeLine);
		SnapShot($vmWareMQTT, $snapshotspNbJours, $logTimeLine);
	}

	if ($savGdrive) {
		//=============================================================================================================
		mg::MessageT('', "! SAV GDRIVE");
		//=============================================================================================================
			$messageBackup = " Daily - GDrive Lancé.";
		mg::setCmd($equipBackupGDrive, 'mg');
		mg::setCmd($equipBackupGDrive, 'Desktop');
		mg::setCmd($equipBackupGDrive, 'customTemplates');
		mg::setCmd($equipBackupGDrive, 'Backup');
		$messageBackup = " Daily - GDrive Terminé.";
	}
}

// **************************************************************************************************************************
// **************************************************************************************************************************
// **************************************************************************************************************************
function SnapShot($equipVmWare, $snapshotspNbJours, $logTimeLine) {

// ****************************** NB : NE PAS OUBLIER D'ACTIVER LE SERVICE SSH SUR ESXI ******************************

mg::MessageT('', ". ***************************** NETTOYAGE SNAPSHOTS DE $equipVmWare ******************************");
	mg::getCmd($equipVmWare, 'Liste des snapshots');
	mg::setCmd($equipVmWare, 'Rafraichir');
	$liste = mg::getCmd($equipVmWare, 'Liste des snapshots');
	$liste = explode(",", $liste);
	for ($i = 0; $i < count($liste); $i++) {
		$nomSnap = $liste[$i];
		$dates = explode(" ", $liste[$i]);
		if (!is_array($dates) || count($dates) < 3) { continue; }
		$datePart = @explode("-", $dates[3]);
		$date = @mktime(0, 0, 0, @$datePart[1], @$datePart[0], @$datePart[2]);

		// Supprime les snaphot automatique du jour ou de plus de $snapshotspNbJours jours
			$diffDate = round((time() - $date) / 24/3600, 0);
			if (strpos($liste[$i], 'Snapshot automatique') !== false && ($diffDate == 0 || $diffDate >= $snapshotspNbJours)) {
				mg::setCmd($equipVmWare, 'Supprimer un snapshot', '', "Nom=\"$nomSnap\"");
				mg::message('', "Suppression de '$liste[$i]' - Ancienneté : $diffDate - Jour : ". date('j', $date) . ' / ' . date('D', $date));
			}
	}

	// Création du SnapShot
	if (mg::getCmd($equipVmWare, 'Online') == 'Oui') {
	mg::MessageT('', ". **************************** CREATION SNAPSHOT DE $equipVmWare *****************************");
	  $nomSnap = "Snapshot automatique du " . date('d\-m\-Y \à H\hi\m\n', time());
	  $descriptionSnap = "Snapshot créé automatiquement.";
	  $title = "Nom=\"$nomSnap\" Description=\"$descriptionSnap\"";
	  mg::setCmd($equipVmWare, 'Prendre un snapshot', 'OUI', $title);
	}
}

?>