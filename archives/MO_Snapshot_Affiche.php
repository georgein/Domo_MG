<?php
/***********************************************************************************************************************/
													function MO_Snapshot_Affiche__100()
/***********************************************************************************************************************/
Sheduler : 0 * * * *

Trigger (0) : #[Maison][Affichage SnapShot][Demande]#

/************************************************************************************************************************
Snapshot Affiche - 100
************************************************************************************************************************/
include_once getRootPath() . '/mg/mg.class.php'; mg::init();

$EquipVirtuel = '#[Maison][Affichage SnapShot]#';						// Nom de l'équipement virtuel concerné

// ******************************************** Suite du code du scénario ***********************************************
//include_once getRootPath() . '/mg/MO_Snapshot_Affiche.php';


/**********************************************************************************************************************
Snapshot Affiche - 100
Tri et affichage des snapshots.
Le rafraichissement du répertoire de destination effectue une sélection dans le répertoire d'origine (si Timing > 0)
et déplace les fichiers survivants vers le répertoire d'origine.
La purge supprime tous les fichiers .jpg du répertoire destination de plus de $DureePurge heures.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$EquipVirtuel

// N° des scénarios :

//Variables :
	$RepDest = "/var/www/html/mg/Snapshots/"; // Répertoire de stockage des Snapshots conservés

// Paramètres :

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

// Finalisation des chemins avec '/' final

// Lit le répertoire trié par nom ascendant
$ListeSnapshot = scandir($RepDest, SCANDIR_SORT_ASCENDING);

// Pose des variables
$NbSnapshots = count($ListeSnapshot) - 2;
if ($NbSnapshots == 0) { return; }

$_SnapShotActif = mg::getVar('_SnapShotActif', 1);
$_SnapShotAffichage = mg::getVar('_SnapShotAffichage');
$DeltaPoubelleMin = 0;

// Si scheduler on charge les fichiers des repOrg, sinon on répond à la demande
if (mg::ExtractPartCmd(mg::getTag('#trigger#'), 3) == 'schedule') {
		$Demande = 'Dernier';
} else {
	$Demande = mg::getCmd($EquipVirtuel, 'Demande');
}

mg::Message('', "--------------------------------- TRAITEMENT $Demande ($NbSnapshots) --------------------------------------");
mg::message('', "Affectation du fichier $Demande à l'affichage.");

// Suppression du SnapShot courant
if ( $Demande == 'Poubelle' && $_SnapShotActif > 0 ) {
mg::Message('', "------------------------------------------- Poubelle ----------------------------------------------");
	unlink( $RepDest . mg::getVar('_SnapShotAffichage'));
	$NbSnapshots = $NbSnapshots - 1;
	$Increment = -1;
// Choix de l'incrément
} else if ($Demande == 'Premier') { $Increment = -9999;
} else if ($Demande == 'GrosLotPrecedent') { $Increment = -round($NbSnapshots / 10, 0);
} else if ($Demande == 'LotPrecedent') { $Increment = -round($NbSnapshots / 100, 0);
} else if ($Demande == 'Precedent') { $Increment = -1;
} else if ($Demande == 'Suivant') { $Increment = 1;
} else if ($Demande == 'LotSuivant') { $Increment = round($NbSnapshots / 100, 0);
} else if ($Demande == 'GrosLotSuivant') { $Increment = round($NbSnapshots / 10, 0);
} else { $Increment = 9999; }

// Calcul du nouveau SnapShot actif
if ( $Demande == 'Poubelle' && $_SnapShotActif == 1 ) { $DeltaPoubelleMin = 1;
} else {
	$_SnapShotActif = $_SnapShotActif + $Increment;
	if ($_SnapShotActif <= 1) { $_SnapShotActif = 1; }
	if ($_SnapShotActif > $NbSnapshots) { $_SnapShotActif = $NbSnapshots; }
}

// Calcul nouvel affichage
if ($NbSnapshots > 0) {
	mg::setVar('_SnapShotAffichage', $ListeSnapshot[$_SnapShotActif +1 + $DeltaPoubelleMin]);
// Si aucun Snapshot
} else {
	$_SnapShotActif = 0;
	mg::setVar('_SnapShotAffichage', '');
}

// Mémo des variables
mg::setVar('_SnapShotActif', $_SnapShotActif);
mg::setVar('SnapShotCount', "$_SnapShotActif / $NbSnapshots");

mg::Message('', "-------------------------------------------- FIN --------------------------------------------------");
mg::setCmd($EquipVirtuel, 'Nom_Snapshot_', mg::getVar('_SnapShotAffichage', '_____') . " (" . $_SnapShotActif . " / " . $NbSnapshots. ").");

?>