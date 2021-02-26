<?php
/********************************************************************************************************************************
Controle Daemon
Contrôle l'état des daemons du tableau et éventuellement les relance si KO
*********************************************************************************************************************************/

// Infos, Commandes et Equipements :
//

// N° des scénarios :

//Variables :
	$tabDaemon = array(					// Tableau des daemons à contrôler
					'openzwave',
//					'rfxcom',
//					'broadlink',
//					'xiaomihome',
//					'blea',
	  );

// Paramètres :

/*******************************************************************************************************************************/
/*******************************************************************************************************************************/
/*******************************************************************************************************************************/

for ($i = 0; $i < count($tabDaemon); $i++) {
	$daemonInfo = $tabDaemon[$i]::deamon_info();
	
	if ($daemonInfo['state'] == 'ok') {
	} else { 
	mg::Message('', $tabDaemon[$i] . ' ==> ' . $daemonInfo['state']);
/*		// ********************* Pour ZWave ajouter la ligne suivante **************************
		//shell_exec('sudo pkill -f openzwaved.py');
		// *************************************************************************************
		
		// Relance des daemon
		$DaemonStop = $tabDaemon[$i]::deamon_stop();
		mg::Message('Log,Message', "$tabDaemon[$i] : deamon_stop : $DaemonStop");
		sleep(5);
		$DaemonStart = $tabDaemon[$i]::deamon_start();
		mg::Message('Log,Message', "$tabDaemon[$i] : deamon_start : $DaemonStart");
*/
	}
}

?>