<?php
/***********************************************************************************************************************/
													function CO_Aromatiseur__118()
/***********************************************************************************************************************/
Sheduler : 11 02 02 09 * 2018

Trigger (0) : #start#
Trigger (1) : #variable(Alarme)#
Trigger (2) : #variable(NuitSalon)#

/************************************************************************************************************************
Aromatiseur - 118
************************************************************************************************************************/
include_once getRootPath() . '/mg/mg.class.php'; mg::init();

$EquipAromatiseur = '#eqLogic633#';

// ******************************************** Suite du code du scénario ***********************************************
//include_once getRootPath() . '/mg/CO_Aromatiseur.php';


/**********************************************************************************************************************
function Aromatiseur 118()
L'aromatiseur est déclenché à la périodicité demandé la nuit si pas en Alarme.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$EquipAromatiseur

// N° des scénarios :

//Variables :
	$Alarme = mg::getVar('Alarme');
	$NuitSalon = mg::getVar('NuitSalon');

// Paramètres :
	$AromPeriode = mg::getParam('AromPeriode');			// Période de déclenchement en mn.
	$AromDuree = mg::getParam('AromDuree');				// Durée d'aromatisation en mn
	$AromTypeMarche = mg::getParam('AromTypeMarche');		// Type de fonctionnement ('Brumisateur On/Toggle', 'Lumière On/Toggle', 'Tout On')

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

$Aromatiseur = mg::getVar('_Aromatiseur');

if ( $Aromatiseur != 0 || $Alarme == 1 || $NuitSalon == 2 ) {
mg::Message('', "------------------------------------------- ARRET -------------------------------------------------");
	mg::setCmd($EquipAromatiseur, 'Off');
	mg::unsetVar('_Aromatiseur');
	// Pose du cron de redémarrage
	mg::setCron('', time() + (($AromPeriode - $AromDuree) *60));

} else {
mg::Message('', "----------------------------------------- LANCEMENT -----------------------------------------------");
	mg::setCmd($EquipAromatiseur, $AromTypeMarche);
	$HeureLancement = time() + ($AromDuree *60);
	// Pose du cron d'arrêt
	mg::setCron('', $HeureLancement);
	mg::setVar('_Aromatiseur', time());
}

?>