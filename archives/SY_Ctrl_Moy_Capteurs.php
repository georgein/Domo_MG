<?php
/* ********************************************************************************************************************
Ctrl_Moy_Capteurs - 131

Vérifie périodiquement si les capteurs ont envoyé un signal il y a moins de $TimingCtrl heures. en cas de problème => message Jeedom.
Remet à zéro les capteurs en anomalie pour les sousType Vir et Cam.

Enregistre dans une variable (capteur virtuel) la moyenne pondérée et corrigée si l'heure de mise à jour est OK
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//

//N° des scénarios :

// Variables :
	$TabCapteurs = (array)mg::getVar('TabCapteurs');
	$Color = mg::ROUGE; // Couleur des messages d'alerte
	$DestinataireAlarme = 'Message';			// Destinataires des messages d'alarme
	$NuitSalon = mg::getVar('NuitSalon');
	$Alarme  = mg::getVar('Alarme');

//Paramètres :
	$IP_Jeedom = mg::getTag('#IP#');
	$API_Jeedom = mg::getVar('API_Jeedom');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

mg::unsetVar('MessageAlerte');;
$MessageAlerte = '';
$CapteursEnErreur = '';
$LogEnErreur = '';

$SommeValeursPonderees = 0;
$NbPondere = 0;
$SommeLastChange = 0;
$Nb = 0;
$OldNomVar = '';
$OldType = '';

foreach ($TabCapteurs as $InfoCapteur => $DetailsCapteur) {
	if ($InfoCapteur == 'FinCapteurs') { goto fin; }
	$SousType = trim($DetailsCapteur[5]);
	$Zone = trim($DetailsCapteur[0]);
	if ( $Zone == 'null') { continue; }
	$ZoneSec = trim($DetailsCapteur[1]);
	$Pondération = floatval($DetailsCapteur[2]);
	$Correction = intval($DetailsCapteur[3]);
	$Type = trim($DetailsCapteur[4]);
	$SousType = trim($DetailsCapteur[5]);
	$TimingCtrl = intval($DetailsCapteur[6]);
	$NomVar = "$Type"."_$Zone";

	$ValCapteur= mg::getCmd($InfoCapteur, '', $CollectDate);
	if (!$CollectDate) {
		mg::Message($DestinataireAlarme, "TAB_CAPTEURS - La commande '$InfoCapteur' n'existe pas !!!");
		continue;
	} else {
		$LastChange_mn = round((time() - strtotime($CollectDate)) / 60);
	}

/*********************************************************************************************************************/
//													RUPTURE SUR $NomVar
/*********************************************************************************************************************/
	if ( $NomVar != $OldNomVar && $OldNomVar != '' && $Type != 'Mvmt' && $Type != 'Porte' ) {
		if ($Nb > 0) {
			if ( $OldType != 'Mvmt' && $OldType != 'Porte' && $Nb != 0 ) { // $NbPondere != 0 ) {////////////////////////////////
				$ValMoyenne = round(($SommeValeursPonderees / $NbPondere), 1);
				mg::setVar($OldNomVar, $ValMoyenne);
				mg::Message('', "$OldNomVar : Moyenne calculée(avec pondération) : $ValMoyenne pour $Nb capteur(s), il y a en moyenne " . round($SommeLastChange / $NbPondere /60, 1) . " mn. ==> $SommeValeursPonderees / $NbPondere");
			}
		} else { mg::Message($DestinataireAlarme, "$OldNomVar ******** AUCUN CAPTEUR ACTIF ********"); }

		mg::Message('', "----------------------------------------- $NomVar -----------------------------------------");

		$SommeValeursPonderees = 0;
		$SommeLastChange = 0;
		$NbPondere = 0;
		$Nb = 0;
	}
/*********************************************************************************************************************/
//													FIN DE RUPTURE SUR $NomVar
/*********************************************************************************************************************/
	$OldNomVar = $NomVar;
	$OldType = $Type;

//---------------------------------------------------------------------------------------------------------------------
	// Si dernière valeur il y a moins de $TimingCtrl minutes on calcul la somme des valeurs pondérées et corrigées
//---------------------------------------------------------------------------------------------------------------------
	if ($TimingCtrl != 9999 && $LastChange_mn < $TimingCtrl) {
		if ($Pondération!= 0) {
			$NbPondere += 1;
			$ValeurCorrigee = $ValCapteur * $Pondération + $Correction;
//			mg::message('', " $ValeurCorrigee = $ValCapteur * $Pondération + $Correction");
			$SommeLastChange += $LastChange_mn ;
			$SommeValeursPonderees += $ValeurCorrigee;
		}
		$Nb += 1 ;
	}
//=====================================================================================================================
//													CAPTEURS EN ANOMALIE
//=====================================================================================================================
	if ($TimingCtrl != 9999 && $LastChange_mn > $TimingCtrl ) {
		$CapteursEnErreur = $CapteursEnErreur . trim(mg::toHuman($InfoCapteur), '#') . " : ($ValCapteur) il y a plus de " . round($LastChange_mn /60) . " h (max : " . round($TimingCtrl/60) . " h).";
	}

} // fin de boucle capteurs
fin:
$CapteursEnErreur;
//=====================================================================================================================
//									SURVEILLANCE DES LOGS TOUTES LES 5 MINUTES
//=====================================================================================================================

	// Problème FREE
	if (mg::getVar('FREE_OK')) {
		$CapteursEnErreur .= '<br>Réseau FREE HS.';
	}

// Log Scénarios en erreur toutes les 5 mn pour économiser le CPU
if (mg::getTag('#minute#') % 5 == 0) {
	$LogEnErreur = shell_exec("sudo grep -rn -o -i 'error' /var/www/html/log/scenarioLog --files-with-matches");

	if ($LogEnErreur) {
		$LogEnErreur = str_replace("/var/www/html/log/", ' - ', $LogEnErreur);
		$LogEnErreur = str_replace("scenarioLog/", '', $LogEnErreur);
		$LogEnErreur = str_replace(".log", '', $LogEnErreur);
		$LogEnErreur = str_replace(" - scenario131", '', $LogEnErreur); // scénario courant 
	}
	// Ajout des messages de capteurs en erreurs	
	if (trim($LogEnErreur)) {
		$LogEnErreur = "<br>ERROR dans :$LogEnErreur<br>";
	}
	
	// Nettoyage log > 1 Mo
	@shell_exec("find /var/www/html/log -type f -size +250k -exec rm -f {} \;"); // Permet d'économiser du CPU
}

$MessageAlerte = mg::NettoieChaine($CapteursEnErreur . $LogEnErreur);
mg::setVar('MessageAlerte', rtrim($MessageAlerte));
mg::message('', $MessageAlerte);

?>