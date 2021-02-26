<?php
/**********************************************************************************************************************
LastMvmt - 98

Met à jour l'état de tous les capteurs de mouvements et des portes. (pour les portes appel via '_InfPorte' postionné par le scénario 'LastPortes - 136')
Pour chaque zone 4 variables sont calculées (plus la pseudo zone 'ALL') :
		NbMvmt$Zone,
		LastMvmt$Zone,
		NbPortes$Zone,
		LastPortes$Zone
ATTENTION : Certain capteurs de mouvement extérieurs sont inhibés par la formule '(variable(!RafaleCptMvmt)	 < #[Extérieur][Ext Général][Raf. max (1 h)]#) ? 0 : #value#' dans l'onglet de configuration du capteur
ATTENTION :	TOUS les déclencheurs doivent être déclarés dans la table @ZoneMotions doivent être réglés à "Jamais répéter" pour économiser le CPU.
REMARQUE : Gère une variable _InfPorte avec le déclencheur pour activer d'autres scénarios sans mettre la liste de tous les déclencheurs d'ouverture.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$InfRafales
// N° des scénarios :

//Variables :
	$Cron = 1; 	// Période du cron du scénario
	$TabCapteurs = (array)mg::getVar('TabCapteurs');

// Paramètres :
	$LogMvmt = mg::getVar('LogMvmt');
	$NbMvmtMax = mg::getVar('NbMvmtMax');					// Nombre maximum de mouvements mémorisé dans NbMvmtxxxx
	$LastMvmtAll = mg::getVar('LastMvmtAll');
	$LastPortesAll = mg::getVar('LastPortesAll');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

// --------------------------------------------------------------------------------------------------------------------
	// Calcul et mémo du flag _VentFort basé sur le les rafales
mg::setVar('_VentFort', mg::getCmd($InfRafales) > mg::getVar('SeuilVentFort'));
// --------------------------------------------------------------------------------------------------------------------

	$VentFort = mg::getCmd('#[Extérieur][Mouvements][VentFort]#');
$Declencheur = mg::getTag('#trigger#');

mg::message('', "Déclencheur : $Declencheur");
if(strpos($Declencheur, '[') !== false) {
	$NomCmdDeclencheur = mg::ExtractPartCmd($Declencheur, 2) . ' / ' . mg::ExtractPartCmd($Declencheur, 3);
	// Init déclencheur par '_InfPorte'
	if (strpos($Declencheur, '_InfPorte') !== false) { $Declencheur = mg::getVar('_InfPorte'); }
}
return;




//------------------------------------------------- APPEL PAR SCHEDULE ------------------------------------------------
// Si lanceur == schedule, calcul des 'Last' globales puis des zones
if (mg::ExtractPartCmd($Declencheur, 3) == 'schedule') {


	if (mg::getVar('NbMvmtAll') == 0) { mg::setVar('LastMvmtAll', $LastMvmtAll+$Cron); }
	if (mg::getVar('NbPortesAll') == 0) { mg::setVar('LastPortesAll', $LastPortesAll+$Cron); }

	$OldType = '';
	$OldZone = '';
	foreach ($TabCapteurs as $InfMotion => $DetailsCapteur) {
		$Zone = trim($DetailsCapteur[0]);
		$Type = trim($DetailsCapteur[4]);
		$VentMaxCapteur = trim($DetailsCapteur[7]);

		if ($Zone != $OldZone && $OldZone != '' && $OldZone != 'FinMotion' && ($OldType == 'Mvmt' || $OldType == 'Porte')) {

				if (mg::getVar("NbMvmt$OldZone") == 0) { mg::setVar("LastMvmt$OldZone", mg::getVar("LastMvmt$OldZone")+$Cron); }
				if (mg::getVar("NbPortes$OldZone") == 0) { mg::setVar("LastPortes$OldZone", mg::getVar("LastPortes$OldZone")+$Cron); }
		}

		$OldZone = $Zone;
		$OldType = $Type;
		if ($InfMotion == 'FinMotion') { return; }
	}
//---------------------------------------------------- FIN APPEL PAR SCHEDULE ----------------------------------------


} else {
//------------------------------------------------------ APPEL PAR CAPTEURS -------------------------------------------
	$Message = '';
	$OldZone = '';
	$NbMvmtAll = 0;
	$NbPortesAll = 0;
	$NbMvmtZone = 0;
	$NbPortesZone = 0;

	// Parcours des capteurs
foreach ($TabCapteurs as $InfMotion => $DetailsCapteur) {
	$Zone = trim($DetailsCapteur[0]);
	$Type = trim($DetailsCapteur[4]);
	$SousType = trim($DetailsCapteur[5]);
	$VentMaxCapteur = trim($DetailsCapteur[7]);
	if ($Type != 'Mvmt' && $Type != 'Porte' && $InfMotion != 'FinMotion') { continue; }

// ----------------------------------------------- Rupture sur $Zone --------------------------------------------------
	if ( $OldZone != $Zone && $OldZone != '' ) {

		//---------------------------------------------------- MàJ des Inf $ZONE ----------------------------------------------
		// --------------- MAJ de NB ZONES ------------------
		mg::setVar("NbMvmt$OldZone", $NbMvmtZone);
		mg::setVar("NbPortes$OldZone", $NbPortesZone);

		// --------------- RAZ des LAST ZONE ---------------
		if ($NbMvmtZone != 0) { mg::setVar("LastMvmt$OldZone", 0); }
		if ($NbPortesZone != 0) { mg::setVar("LastPortes$OldZone", 0); }

		$tmp = " ---- $OldZone : $NbMvmtZone / $NbPortesZone";
		$Message .= $tmp;
		//--------------------------------------------------- MàJ $tmp --------------------------------------------

		$NbMvmtZone = 0;
		$NbPortesZone = 0;
	}
// ---------------------------------------------- Fin de rupture sur $Zone --------------------------------------------

	$OldZone = $Zone;
//=====================================================================================================================
	// --------------------------------Calcul des Mouvements de la 'Zone' ---------------------------------------------
	if ( $Zone != 'null') {
		if (mg::getCmd($InfMotion) > 0) {
			// Si Mvmt il faut pas de VentFort si param VentMaxCapteur != 999 (A gérer aussi dans le virtuel pour l'affichage du design)
			if ( $Type == 'Mvmt' /*&& (!$VentFort || $VentMaxCapteur == 999)*/ ) {
				if ($NbMvmtZone < $NbMvmtMax) { $NbMvmtZone++; }
				if ( $Zone != 'Ext' && $NbMvmtAll < $NbMvmtMax) { $NbMvmtAll++; }
			}
			else if ( $Type == 'Porte') {
			if ($NbPortesZone < $NbMvmtMax) { $NbPortesZone ++; }
mg::message('', "---------------------------------------------------------------------$Zone -- NbPortesZone");
				if ($Zone != 'Ext' && $NbPortesAll < $NbMvmtMax) { $NbPortesAll++; }
			}
		}
	}
} //  Fin de parcours des capteurs

// Pour Debug, log bavard
//mg::message($LogMvmt, "NbMvmt / NbPortes ==> All : $NbMvmtAll / $NbPortesAll $Message ==== ($NomCmdDeclencheur = " . mg::getCmd($Declencheur) . ")" );

// --------------- RAZ des LAST ALL ---------------
if ($NbMvmtAll != 0) { mg::setVar("LastMvmtAll", 0); }
if ($NbPortesAll != 0) { mg::setVar("LastPortesAll", 0); }

// ---------------Enregistrement des NB All -------
mg::setVar('NbMvmtAll', $NbMvmtAll);
if(mg::getVar('NbPortesAll') != $NbPortesAll) { mg::setVar('NbPortesAll', $NbPortesAll); }

if ($InfMotion == 'FinMotion') { return; }
} // Fin d'appel par capteur

?>