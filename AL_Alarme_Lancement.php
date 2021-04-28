<?php
/**********************************************************************************************************************
Test_Alarme Lancement - 152

Contrôle des Codes alarme et autres avant Lancement / Annulation de l'Alarme.

Gestion des paramètres : Inhibition alarme, Contrôle des ouvertures (mode volumètrique si ouverture), inhibition Sirène, inibition TTS
>Gestion des changements de code alarme des user
**********************************************************************************************************************/
deb:
// Infos, Commandes et Equipements :  
//	$infoPorteEntree
//	$equipAlarme, $equipGeneralMaison, $equipEcl, $equipLampeCouleur

// N° des scénarios :
	$scen_GestionAlarme = 64;
	$scenEclairageSalon = 44;
	$scenModeChauffage = 104;

// Variables :
	$alarme = mg::getVar('Alarme');
	$nuitExt = mg::getVar('NuitExt');
	$nbPortes = mg::getCmd($equipGeneralMaison, 'NbPortes');

	$inhibition = mg::getCmd($equipAlarme, 'Inhibition Etat');
	$sirène = mg::getCmd($equipAlarme, 'Sirène Etat');
	$force = mg::getCmd($equipAlarme, 'Force Etat');
	$equiNomUserModif = trim(mg::toID($equipAlarme, 'NomUserModif_'), '#');
	
	$prefixeMessage = date('d/m/Y H:i:s', time());

// Paramètres :
	$tabUser = mg::getVar('tabUser');
	$tab_Password = mg::getVar('tabPassword');

	$logAlarme = mg::getParam('Log', 'alarme');					// Pour debug
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$timingAlarmeEntree = mg::getParam('Alarme', 'timingEntree');	// Temps maximum (en mn depuis le dernier mouvement de la porte d"entrée pour autoriser le lancement de l"alarme si AutoPrésence.
	$prefixeAlarme = mg::getParam('Alarme', 'prefixe');			// Préfixe du message d'alarme
	$destinatairesAlarme = mg::getParam('Alarme', 'destinataires');// Destinataires des messages d'alarme

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$debugAlarme = 0;

/*// ***** INIT de la table PASSWORD *****
$tab_Password = array(
'Options' => array('mdp' => '31416#'),
'MG' => array('mdp' => '#31416'),
'NR' => array('mdp' => '121060'),
'PS' => array('mdp' => '#12345'),
'CB' => array('mdp' => '081147'),
'Invité' => array('mdp' => '#1234#'),
'RETOUR' => array('mdp' => '!')
);

mg::setVar('tabPassword', $tab_Password);
mg::message('', print_r($tab_Password, true));
return;*/

// Nettoyage si rien à faire
if (mg::getCmd($equipAlarme, 'CodeSaisi') == '' && mg::getCmd($equipAlarme, 'NomUserModif') == '') {
	mg::setInf($equipAlarme, 'NomUserModif', '');
	mg::setInf($equipAlarme, 'OptionsHTML', '');
	mg::setInf($equipAlarme, 'BoutonsHTML', '');
}

$codeSaisi = mg::getCmd($equipAlarme, 'CodeSaisi');
$nomUserModif = mg::getCmd($equipAlarme, 'NomUserModif');
$nomUserSaisi = '';
$message = '';
mg::setInf($equipAlarme, 'MessageAlarme', '');

if (mg::declencheur('NomUserModif')) {
	// ****************************************************************************************************************
	mg::MessageT('', "! GESTION DE LA MODIFICATION DE $nomUserModif");
	// ****************************************************************************************************************
	if($nomUserModif != 'RETOUR') {
		mg::setInf($equipAlarme, 'MessageAlarme', "Saisissez le nouveau code pour le user '$nomUserModif'");
	}
	// Nettoyage en sortie d'options
		mg::setInf($equipAlarme, 'OptionsHTML', '');
		mg::setInf($equipAlarme, 'BoutonsHTML', '');
		mg::setInf($equipAlarme, 'CodeSaisi', '');
		mg::setInf($equipAlarme, 'NomUserSaisi', '');
		return;
}

if (mg::declencheur('CodeSaisi') && $nomUserModif &&  $nomUserModif != 'RETOUR') {
	// ****************************************************************************************************************
mg::MessageT('', "! CHANGEMENT MOT DE PASSE $nomUserModif => $codeSaisi");
	// ****************************************************************************************************************
	$message = "Le Code de '$nomUserModif' à été modifié en '$codeSaisi'.";
	mg::setInf($equipAlarme, 'MessageAlarme', $message);
	$tab_Password[$nomUserModif] = $codeSaisi;
	mg::setVar('tabPassword', $tab_Password);
	mg::message($logTimeLine, "Alarme - $message");

	// Nettoyage après changement de code
	mg::setInf($equipAlarme, 'OptionsHTML', '');
	mg::setInf($equipAlarme, 'BoutonsHTML', '');
	mg::setInf($equipAlarme, 'CodeSaisi', '');
	mg::setInf($equipAlarme, 'NomUserModif', '');
	mg::setInf($equipAlarme, 'NomUserSaisi', '');
	sleep(5);
	mg::setInf($equipAlarme, 'MessageAlarme', '');
	return;
}

if (mg::declencheur('Sirène') || mg::declencheur('Inhibition') || mg::declencheur('Force')) {
	// ****************************************************************************************************************
		mg::MessageT('', "! MAJ DES BOUTONS");
	// ****************************************************************************************************************
	// Actualisation après action bouton
	MakeBoutonsHTML($equipAlarme);
	MakeOptionsHTML($equipAlarme, $tabUser, $tab_Password, $equiNomUserModif);
	mg::setInf($equipAlarme, 'CodeSaisi', '');
	mg::setInf($equipAlarme, 'NomUserModif', '');
	mg::setInf($equipAlarme, 'NomUserSaisi', '');
	return;
	}

if (mg::declencheur('CodeSaisi') && $codeSaisi != '000000') {
	// ****************************************************************************************************************
	mg::MessageT('', "! VERIFICATIN LOGIN $codeSaisi");
	// ****************************************************************************************************************
	$nomUserSaisi = verifCode($tabUser, $tab_Password, $codeSaisi);
	mg::setInf($equipAlarme, 'NomUserSaisi', $nomUserSaisi);
	mg::MessageT('', ". CODE : $codeSaisi => user : $nomUserSaisi");

	if (mg::getCmd($equipAlarme, 'NomUserSaisi') == 'Options') {
	// ****************************************************************************************************************
		mg::MessageT('', "Gestion du user Options");
	// ****************************************************************************************************************
		MakeOptionsHTML($equipAlarme, $tabUser, $tab_Password, $equiNomUserModif);
		MakeBoutonsHTML($equipAlarme);
	} else {
		// Nettoyage si pas Options
		mg::setInf($equipAlarme, 'OptionsHTML', '');
		mg::setInf($equipAlarme, 'BoutonsHTML', '');
		mg::setInf($equipAlarme, 'CodeSaisi', '');
	}
}

//---------------------------------------------------------------------------------------------------------------------
//											APPEL PAR SCENARIO ==> AUTOPRESENCE
//---------------------------------------------------------------------------------------------------------------------
if ( mg::declencheur('scenario')) {
	$nomUserSaisi = 'AutoPrésence';
}

//---------------------------------------------------------------------------------------------------------------------
//											SIGNALEMENT ALARME INHIBEE et LOGIN INCORRECT
//---------------------------------------------------------------------------------------------------------------------
if ($inhibition) {
		mg::setVar('Alarme_Aff', -2);
		$message = "Alarme Inhibée !<br>";
} else {
	mg::unsetVar('Alarme_Aff');

	if ($nomUserSaisi == '!'  ) { $message = "Login Alarme : Code incorrect !"; }
	if ($message) { mg::message($destinatairesAlarme, $message, '.'); }
}

//Si Forçée ou alarme désactivée et user OK et pas inhibée on l'active
if (($force || ($alarme == 0 && $nomUserSaisi && $nomUserSaisi != '!'  && $nomUserSaisi != 'Options') && !$inhibition)) {

// --------------------------------------------------------------------------------------------------------------------
mg::MessageT('', "! ****************************************** ACTIVATION ******************************************");
// --------------------------------------------------------------------------------------------------------------------
	// Si lancement Autopresence
	// on annule si porte d'entrée non ouverte depuis plus de $timingAlarmeEntree
	if ($nomUserSaisi == 'AutoPrésence') {
		$dureePorte = scenarioExpression::lastChangeStateDuration($infoPorteEntree, 1)/60;
		if ($dureePorte >= $timingAlarmeEntree) {
			$message = "Lancement de l'alarme annulée (pas de porte d'entrée depuis " . round($dureePorte, 0) . "minutes.";
			mg::Message($logTimeLine, "Alarme - $message");
			mg::Message($destinatairesAlarme, $message, $prefixeAlarme);
			goto fin;
		}
		if (mg::getCmd($infoPorteEntree)) {
			$message = "La porte d'entrée est ouverte. Armement de l'alarme impossible !";
			mg::Message($destinatairesAlarme, $message, $prefixeAlarme);
			goto fin;
		}
	}
	// Passage en mode Alarme
//	mg::setInf($equipAlarme, 'Perimetrique Etat', !$nbPortes > 0 ? 1 : 0);
	$message = "Alarme activée par " . ($force ? 'le mode FORCE' : $nomUserSaisi);
	if ($debugAlarme) { goto fin; }

	mg::setVar('Alarme', 1);

	// Stop Snaphot avant volet et Stop lumière	 relancement après les volets
		mg::VoletsGeneral ('Salon, Chambre, Etage', 'D');

		mg::setCmd($equipEcl, 'Lampe Générale Slider', 0);
		mg::setScenario($scenEclairageSalon, 'stop');

	// Gestion des chauffages
	sleep(10); // Pour donner le temps à la var 'Alarme' de neutraliser le mode 
	mg::setScenario($scenModeChauffage, 'stop'); 
	mg::setVar('_TypeChauffage', 'Eco');

	mg::Message($logTimeLine, "Alarme - $message");
	mg::Message($destinatairesAlarme, $message, $prefixeAlarme);
	mg::LampeCouleur($equipLampeCouleur, 80, mg::ROUGE, '', 180);

	mg::setScenario($scen_GestionAlarme, 'activate');
} // Fin d'activation

//Si Inhibée ou demande de désactivation et user OK on Désactive
elseif (!$alarme || mg::getCmd($equipAlarme, 'Inhibition Etat') || ($alarme == 1 && $nomUserSaisi && $nomUserSaisi != '!' && $nomUserSaisi != 'Options')) {

// --------------------------------------------------------------------------------------------------------------------
mg::MessageT('', "! ***************************************** DESACTIVATION ****************************************");
// --------------------------------------------------------------------------------------------------------------------
	// Desactivation de l'alarme proprement dite
	mg::unsetVar('Alarme');

	$message = "Alarme désactivée par " . ($inhibition ? 'le mode INHIBITION' : $nomUserSaisi);
	mg::Message($logTimeLine, "Alarme - $message");
	mg::Message($destinatairesAlarme, $message, $prefixeAlarme);
	mg::LampeCouleur($equipLampeCouleur, 80, mg::VERT, '', 180);
	mg::setScenario($scen_GestionAlarme, 'deactivate');
		if ($debugAlarme) { goto fin; }

	// Stop Snapshot avant volet (Alarme == 0) et relancement après les volets Lumière ON
	if ($nuitExt == 0) {
		mg::VoletsGeneral('Salon, Chambre, Etage', 'M');
	}

	// RAllumage salon
	mg::setScenario($scenEclairageSalon, 'start');
	mg::setCmd($equipEcl, 'Lampe Générale Slider', 99);

	// Retablissement chauffage
	mg::setVar('_TypeChauffage', 'Auto');
	mg::setScenario($scenModeChauffage, 'start');

	// Si alarme desactivée on supprime les variables
	mg::unsetVar('_Alerte_Debut');
	mg::unsetVar('_Alarme_Debut');
	mg::unsetVar('_Alarme_Perimetrique');
} // Fin de désactivation

fin:

if ($message) { mg::setInf($equipAlarme, 'MessageAlarme', "$prefixeMessage<br>$message"); }

// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------

function verifCode($tabUser, $tab_Password, $codeSaisi) {
	$nomUserSaisi = '!';
	foreach ($tab_Password as $user => $detailsUser) {
		mg::message('', "====> $user - ". $detailsUser['mdp']);
		if ($user == 'RETOUR'  || $user == '') { continue; }
		if ($detailsUser['mdp'] != $codeSaisi) {
		// nettoyage des users inutilisés ou supprimé.
			if ($tabUser[$user]['type'] != 'user') {
			}
			continue;
		}
		$nomUserSaisi = $user;
		mg::message('', "$nomUserSaisi -> $codeSaisi");
	}
	mg::setVar('tabPassword', $tab_Password);
	return $nomUserSaisi;
}

// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------

function MakeBoutonsHTML($equipAlarme) {

$pathImg = 'mg/img/img_Binaire/boutons/Circle_';

$imgSirèneEtat = (mg::getCmd($equipAlarme, 'Sirène Etat') == 1 ? 'OFF' : 'ON');
$imgInhibitionEtat = (mg::getCmd($equipAlarme, 'Inhibition Etat') == 1 ? 'OFF' : 'ON');
$imgForceEtat = (mg::getCmd($equipAlarme, 'Force Etat') == 1 ? 'OFF' : 'ON');

$equiSirène = trim(mg::toID($equipAlarme, "Sirène_$imgSirèneEtat"), '#');
$equiInhibition = trim(mg::toID($equipAlarme, "Inhibition_$imgInhibitionEtat"), '#');
$equiForce = trim(mg::toID($equipAlarme, "Force_$imgForceEtat"), '#');

	$HTML = "
	<div>
	<table class=tableauBoutons style=width:100px;>
	<colgroup>
		<col style=width:80px>
	</colgroup>

	<tr>
		<td class='titre_MG nom'>Sirene</td>
	</tr>
	<tr>
		<td class='boutonOnOff Sirene'> <button> <img src='$pathImg$imgSirèneEtat.png'> </button>	</td>
		<script>
			$('.Sirene').on('click',function(){
				jeedom.cmd.execute({id: '$equiSirène'});
			});
		</script>
	</tr>
	<tr>
		<td class='titre_MG nom'>Inhibition Alarme</td>
	</tr>
	<tr>
		<td class='boutonOnOff Inhibition'> <button> <img src='$pathImg$imgInhibitionEtat.png'> </button>	</td>
		<script>
			$('.Inhibition').on('click',function(){
				jeedom.cmd.execute({id: '$equiInhibition'});
			});
		</script>
	</tr>
	<tr>
		<td class='titre_MG nom'>Force Alarme</td>
	</tr>
	<tr>
		<td class='boutonOnOff Force'> <button> <img src='$pathImg$imgForceEtat.png'> </button>	</td>
		<script>
			$('.Force').on('click',function(){
				jeedom.cmd.execute({id: '$equiForce'});
			});
		</script>
	</tr>
</table>
<STYLE type=text/css>

.tableauBoutons {
	text-align: center;
	    background-color: transparent!important
	}

.nom {
	font-size: 1.4em!important;
	color: var(--MG-titre-color);
	}

.boutonOnOff{
	transform: scale(0.8);
}
	margin: 3px 0px 3px 0px;						/* haut | droit | bas | gauche */
	padding: 0px 5px 0px 5px;						/* haut | droit | bas | gauche */
	}
</STYLE>
</div>";

mg::setInf($equipAlarme, 'BoutonsHTML', $HTML);
}

// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------

function MakeOptionsHTML($equipAlarme, $tabUser, $tab_Password, $equiNomUserModif) {

$HTML = "
	<div>
	<div class=digicodeOptions>
	<table class=tableau style=width:150px;>
	<colgroup>
			<col style=width:60px>
			<col style=width:90px>
		</colgroup>
	<tr>
		<th class=titre colspan=2>Liste des Users</th>
	</tr>
	<tr>
		<td class=t-0>Modif</td>
		<td class=t-0>Code</td>
		</tr> ";

	// Boucle des Users


	$lgn = 0;
	foreach ($tab_Password as $user => $detailsUser) {
			$Code = $tab_Password[$user]['mdp'];
			mg::message('', "************* $user - $Code");
			if ($lgn == 0) { $lgn = 1; } else { $lgn = 0; }
			$btAction = "class='bouton'></button>
";

$HTML .= "
				<tr>
					<td class='bouton action-$user'></button> $user	</td>
					<td class=c-$lgn-2>$Code</td>
					<script>
						$('.action-$user').on('click',function(){
							jeedom.cmd.execute({id: '$equiNomUserModif', value: {slider: '$user'}});
						});
					</script>
				</tr> 
";
}

$HTML .="</table>";

// Bouton de sortie
$HTML .="
	<table class=tableauOptions style=width:auto;>
		<td class='bouton RETOUR'>RETOUR</td>
		</table>
		<script>
			$('.RETOUR').on('click',function(){
				jeedom.cmd.execute({id: '$equiNomUserModif', value: {slider: 'RETOUR'}});
			});
		</script>
	</div>";

$HTML .= "
<STYLE type=text/css>
.digicodeOptions {
  line-height:20px;
  margin: 0px;
}

.tableauOptions {
	line-height: 15px;
	}

.th{
	font-size: 1.4em!important;
	text-align: center; !important
	}

.titre{
	text-align: center!important;
	font-size:20px!important;
	line-height: 23px;
	color:var(--MG-titre-color);
	background-color:#680100;
	}

.t-0{font-size: 1.4em!important;font-weight:bold;background-color:#f56b00;color:#ffffff;}

.c-1-2{font-size: 1.4em!important;text-align: center;font-weight:bold;background-color:#ddddddc9;color:#1c1e22;}
.c-0-2{font-size: 1.4em!important;text-align: center;font-weight:bold;background-color:#ddddddc9;color:#1c1e22;}

.bouton{
	font-size: 1.4em!important;
  color:black;
	width:auto;
	background: var(--MG-titre-color);
	border-radius:13px;
	margin: 3px 0px 3px 0px;						/* haut | droit | bas | gauche */
	padding: 0px 5px 0px 5px;						/* haut | droit | bas | gauche */
	display: inline-block;
	border: 2px solid rgba(255, 255, 255, 0.25);
	}
</STYLE>
</div>";

mg::setInf($equipAlarme, 'OptionsHTML', $HTML);
}

?>