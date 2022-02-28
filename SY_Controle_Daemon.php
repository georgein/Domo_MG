<?php
/********************************************************************************************************************************
Controle Daemon
Contrôle l'état des daemons du tableau et éventuellement les relance si KO
*********************************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$infRouteurBroadlink

// N° des scénarios :

//Variables :
	$tabDaemon = array(					// Tableau des daemons à contrôler
//					'rfxcom',
//					'broadlink',
//					'xiaomihome',
//					'blea',
	  );

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');

/*******************************************************************************************************************************/
/*******************************************************************************************************************************/
/*******************************************************************************************************************************/
// Relance de BROADLINK si arrété ou pas sur le réseau
//if (!mg::getCmd($infRouteurBroadlink)) {
if (mg::stateDaemon('broadlink', 1) != 'ok') mg::message($logTimeLine, "Daemon - Relance de broadlink.");
//}

// ON/OFF GCast si offLine
if (!mg::getCmd('#[Sys_Comm][Google-Home][Online]#')) {
	mg::setCmd('#[Salon][Multi-prises][Off_2]#');
	sleep(60);
	mg::setCmd('#[Salon][Multi-prises][On_2]#');
	
	mg::message($logTimeLine, "Daemon - Relance de Gcast.");
}



/*
for ($i = 0; $i < count($tabDaemon); $i++) {
	$daemonInfo = $tabDaemon[$i]::deamon_info();

	if ($daemonInfo['state'] == 'ok') {
	} else {
	mg::Message('', $tabDaemon[$i] . ' ==> ' . $daemonInfo['state']);
		// ********************* Pour ZWave ajouter la ligne suivante **************************
		// *************************************************************************************

		// Relance des daemon
		$DaemonStop = $tabDaemon[$i]::deamon_stop();
		mg::Message('Log,Message', "$tabDaemon[$i] : deamon_stop : $DaemonStop");
		sleep(5);
		$DaemonStart = $tabDaemon[$i]::deamon_start();
		mg::Message('Log,Message', "$tabDaemon[$i] : deamon_start : $DaemonStart");

	}
}
*/

?>