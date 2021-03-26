<?php
/**********************************************************************************************************************
Test_PHP - 152
	Remplace le système d'interaction de Jeedom et sans paramètrages (hors ce programme)
	Reçoit en entrée un message de demande textuel du design, par SMS, PushBullet, Telegram ou une demande vocale de JPI.
	Recherche une commande On/off/Slider/Info en BdD et la lance.
	Répond au message d'entrée

-----------------------------------------------------------------------------------------------------------------------

Le système permet d'actionner n'importe quel appareil affichés par Jeedom dont on connait le nom
Il permet aussi d'avoir des infos sur n'importe quels capteurs affichés dont on connait le nom

A remarquer les raccourcis rdc, etg, ext

Deux manières d'interroger Jeedom, en formule courte pour l'écrit ou en formule longue pour la commande vocale.

voir Quelques exemples ci après servant pour le test:

**********************************************************************************************************************/

//Infos, Commandes et Equipements :
	// $telegram, $infVRstate

//N° des scénarios :

// Variables :
	$debug = 0;		// ******************* METTRE A 1 POUR UTILISER LA LISTE DE BUG setDefDebug(() ********************
//	$silencieux = 1;

//Paramètres :


/*--------------------------------------------------------------------------------------------------------------------*/
/*---------------------------------------------------- GESTION DES REGEXP ---------------------------------------------*/
/*--------------------------------------------------------------------------------------------------------------------*/

global $tabRegex, $telegram, $org, $expediteur, $index, $silencieux, $infReponse;

// DEFINITIONS DES REGEXP PRINCIPALES A PEAUFINER EVENTUELLEMENT MANUELLEMENT
$tabRegex['Slider'] = "(^|\s)(ambiance|Slider|r.gle.?|(met(s|tre)?)|mais)(\s|$)";
$tabRegex['On'] = "(^|\s)(on|allume.?|Ouvre(s|ir)?|lance.?|monte.?|(maxi(mum)?)|(route|marche))(\s|$)";
$tabRegex['Off'] = "(^|\s)(Of(f)?|.tein(s|t|d|dre)?|(arr.te)|baisse.?|descend(re)?|ferme.?|stoppe.?|arr.te|z.ro|(mini(mum)?))(\s|$)";

// REGEXP des protocoles physique à utiliser
$tabRegex['Protocoles'] = "openzwave|rfxcom|deconz|xiaomihome";

setRegexInfo();
// Si nécessaire ajouter ici les commandes (MONO-mots) des virtuels qui ne seraient pas présents dans les équipements physiques
$tabRegex['Info'] = $tabRegex['Info'] . "|consigne|vitesse|cap";

// Mettre ici les mots "parasites ne devant pas être pris en compte"
$tabRegex['motsParasites'] = "(^|\s)((\s\s)|fabienne|d_|accord|alors|(ok),?|parfait|mais|met.|tu|et|(quel)(le)?|est|(donne)(\s|-|s)?(moi)?|moi|puis|depuis|ensuite|apr.s|continue|finalement)(\s|$)";

/*--------------------------------------------- REGEXP GENEREES AUTOMATIQUEMENT --------------------------------------*/
// REGEXP DES LOCALISATIONS
setRegexLocalisations();

// REGEXP LISTE DES EQUIPEMENTS
setRegexListeEquipVirtuel();

// REGEXP DES EQUIPEMENTS
setRegexEquipVirtuel();

// REGEXP OBJETS et OBJETS_COMP
setRegexObjets();

// REGEXP provisoire permettant le filtage des objets
unset($tabRegex['globalVirtuel']);
mg::message('', print_r($tabRegex, true));

/*-------------------------------------------------------------------------------------------------------------------*/
/*------------------------------------------------* RECUPERATION DU MESSAGE -----------------------------------------*/
/*-------------------------------------------------------------------------------------------------------------------*/
$declencheur = mg::declencheur();

$org = '';
$message = '';
if(strpos($declencheur, 'Interaction') !== false) {
	$org = 'JEEDOM';
	mg::message('', "déclencheur : $declencheur");
	$message = mg::getCmd($declencheur);
}
elseif(strpos($declencheur, 'Telegram') !== false) {
	$org = 'TELEGRAM';
	$message = mg::getCmd($declencheur);
}
elseif( strpos($declencheur, 'PushBullet') !== false) {
	$org = 'PUSHBULLET';
	$message = mg::getCmd($declencheur);
}
elseif (mg::gettag('org') == 'JPI_SMS') {
  $org = 'JPI_SMS';
  $message = mg::getTag('message');
  $expediteur = mg::getTag('expediteur');
}
elseif (mg::gettag('org') == 'JPI_INTERACTION') {
  $org = 'JPI_INTERACTION';
  $message = mg::getTag('message');
}

/*-------------------------------------------------------------------------------------------------------------------*/
/*---------------------------------------------------- LANCEMENT NORMAL ---------------------------------------------*/
/*-------------------------------------------------------------------------------------------------------------------*/
if ($declencheur != 'user') {
	mg::messageT('', ".Base($org)  ==> $message");
	if ($message == '') { return; }
		a_decoupeMessage($message);
}

/*-------------------------------------------------------------------------------------------------------------------*/
/*------------------------------------* MODIF DU STATUT DE LA VR de JPI VIA LE WIDGET -------------------------------*/
/*-------------------------------------------------------------------------------------------------------------------*/
if ($declencheur == 'user') {
	if ($debug == 0) {
		if (mg::getCmd($infVRstate) > 0) {
			mg::messageT('', "! Mise en veille (pause) de la reconnaissance vocale");
			mg::JPI('SCENARIO', '_veilleVR');
		} else {
			mg::messageT('', "! Activation de la reconnaissance vocale");
			mg::JPI('SCENARIO', '_activeVR');
		}

/*-------------------------------------------------------------------------------------------------------------------*/
/*------------------------------------------------------ POUR DEBUG -------------------------------------------------*/
/*-------------------------------------------------------------------------------------------------------------------*/
	} else {
		$messages = setDebug();
		$silencieux = 1;
		$org = 'JEEDOM';
		for ($i=0; $i < count($messages); $i++) {
			$message = $messages[$i];
			if ($message == '') { return; }
			$index = $i;
		$return = a_decoupeMessage($message);
			mg::message('', ".");
			mg::message('', ".");
			if ($return < 0) { return $return; }
		}
	}
}

//********************************************************************************************************************/
/******************************** DECOUPE LA DEMANDE EN PHRASE DE SOUS DEMANDE ***************************************/
/*********************************************************************************************************************/
function a_decoupeMessage($message) {
	global $tabRegex, $index;
	$phrases = array('0'=>'');

	// gestion des apostrophes (le remplacement inverse se fait dans 'g_setReponse' en fin de traitement)
	$message = str_ireplace(array('l\''), 'l_ ', $message);
	$message = str_ireplace(array('d\''), 'd_ ', $message);

	
	// Suppression de tous les mots parasites de la phrase de base
	$mots = explode(' ', $message);
	do {
		$mots = preg_replace("/".$tabRegex['motsParasites'] . "/ui", ' ', $mots, -1, $count);
	} while($count > 0);
	
	// EXTRACTION DES PHRASES de requète
	$n = 0;
	$regex = "/(".$tabRegex['On']."|".$tabRegex['Off']."|".$tabRegex['Slider']."|".$tabRegex['Info'].")/ui";
	foreach($mots as $key => $mot) {
			preg_match($regex, $mot, $found);
			if (@iconv_strlen($found[0]) > 1) {
				$n++;
				$phrases[$n] = '';
				// recherche et pose du préfixe pronom
				if ($key >=1 && array_search($mots[$key-1], array('le', 'la', 'les', 'du', 'des', 'l_', 'd_')) !== false) {
					$phrases[$n] .= $mots[$key-1];
				}
			}
			if ($n >= 0) { $phrases[$n] .= " $mot "; }
	}
	mg::message('', "\n$message\n" . print_r($phrases, true));

	// LANCEMENT DES PHRASES REQUETES
	foreach($phrases as $key => $phrase) {
		$phrase = str_ireplace('  ', ' ', trim($phrase));
		$nbMots = count(explode(' ', $phrase));
		if ($nbMots >= 2) {
			mg::messageT('', "! Phrase ($index - ($key/" . (count($phrases)-1) . ") - $nbMots mots ==> '$phrase'");
			$return = b_decodeMessage(trim($phrase));
			if ($return < 0) { 
//			mg::setVar('interactReponse', ' ');
				return $return; 
			}
		} else {
			mg::setVar('interactReponse', ' ');
		}
	}
}

/*/*******************************************************************************************************************/
/***************************************** DECODE LE TXT DE LA DEMANDE ***********************************************/
/*********************************************************************************************************************/
function b_decodeMessage($message) {
	$message = c_nettoieMessage($message);

	if (trim($message) == '') { return; }

	// Init du tableaux de synthèse
	unset($r);
	$r = array('message'=>'', 'equipVirtuel'=>'', 'localisation'=>'', 'objet'=>'', 'objetComp'=>'', 'action'=>'', 'typeCmd'=>'', 'cmd'=>'', 'valSlider'=>'', 'motsResiduels'=>'', 'txtCmd'=>'');
	$r['message'] = $message;

	// Recherche de l'EQUIPEMENT VIRTUEL et de l'OBJET
	getEqVirtuel($r);

	// Recherche de l'OBJET
	getObjet($r);

	// Recherche de Localisation et objetComp
	getLocalisations($r);

	// Recherche d'une commande ACTION
	getCmdAction($r);

	// Recherche d'une commande INFO
		if ($r['action'] != 'Action') {
			getCmdInfo($r);
		}

	// ******************************************** NOM TEXTUEL DE LA COMMANDE ********************************************
	$tabMots = explode(' ', $r['message']);
	for ($i = 0; $i < count($tabMots); $i++) {
		if (@iconv_strlen(trim($tabMots[$i])) > 1
		)
		{ $r['txtCmd'] .= "$tabMots[$i] "; }
		$r['txtCmd'] = str_replace($r['action'], '', $r['txtCmd']);
		$r['txtCmd'] = str_replace(intval($r['valSlider']), '', $r['txtCmd']);
		$r['txtCmd'] = str_replace('%', '', $r['txtCmd']);
	}
		$r['txtCmd'] = str_replace('état', '', $r['txtCmd']);
	$r['txtCmd'] = str_replace('  ', ' ', $r['txtCmd']);

	// ************************************************ MOTS RESIDUELS ************************************************
	$separateur = '.*';
	$tabMots = explode(' ', $r['txtCmd']);
	for ($i = 0; $i < count($tabMots); $i++) {
		if (@iconv_strlen(trim($tabMots[$i])) > 3
			&& strpos('etat', $tabMots[$i]) === false
			&& strpos($r['localisation'], $tabMots[$i]) === false
			&& strpos($r['objet'], $tabMots[$i]) === false
			&& strpos($r['objetComp'], $tabMots[$i]) === false
			&& strpos($r['cmd'], $tabMots[$i]) === false
			&& strpos($r['action'], $tabMots[$i]) === false 
		) {
			$r['motsResiduels'] .= trim("($tabMots[$i])$separateur");
		}
	}
	$r['motsResiduels'] = trim($r['motsResiduels'], $separateur);
	$r['motsResiduels'] = str_ireplace('  ', ' ', $r['motsResiduels']);

	// ******************************************************* FIN ********************************************************

	mg::message('', "Tableau du décodage de la requète :" . print_r($r, true));
	mg::message('', " .");

	// Erreur si pas de cmd
	if (!$r['cmd']) {
		g_setReponse("Veuillez reformuler votre demande en précisant l'action désirée (allume, donne-moi, etc...)");
		mg::message('Log:/_debug', "Pas d'action : " .$r['message']); // debug
		return -1;
	}
	// Erreur pas de localisation
	if (!$r['localisation']) {
		g_setReponse("Veuillez reformuler votre demande en précisant la localisation (Salon, Etage, etc...)");
		mg::message('Log:/_debug', "Pas de localisation : " .$r['message']); // debug
		return -1;
	}

	// Lancement de la requète SQL
	$return = d_requete($r);
	if ($return < 0) { return $return; }
}

/*********************************************************************************************************************/
/**************************************************** NETTOIE MESSAGE ************************************************/
/*********************************************************************************************************************/
function c_nettoieMessage($message) {

	$message = trim(strtolower($message));

	// ------------------------------------------- GESTION DES ABBREVIATIONS ------------------------------------------
	$message = str_ireplace(array(' etg'), ' Etage', $message);
	$message = str_ireplace(array(' rdc'), ' Rez-de-Chaussée', $message);
	$message = str_ireplace(array(' sam'), ' Salle à manger', $message);
	$message = str_replace(array(' sdb'), ' Salle de Bain', $message);
	
	// ------------------------------------- Nettoie les pronoms de fin de phrase -------------------------------------
	$message = preg_replace('/\s\D{0,3}$/ui', '', $message);

	// ---------------------------------------------  GESTION DES SYNONYME --------------------------------------------
	$message = str_ireplace(array('general'), 'général', $message);
	$message = str_ireplace(array('état'), 'etat', $message);
	$message = str_ireplace(array('Salle de Bain'), 'Salle de Bain du Rez-de-Chaussée', $message);

	$message = str_ireplace(array('rez de chaussee', 'Rez de Chaussée'), ' Rez-de-Chaussée ', $message);
	$message = str_ireplace(array('exterieur','extérieure'), 'extérieur', $message);
	$message = str_ireplace(array('entree'), 'entrée', $message);
	$message = str_ireplace(array('degré ','temperature'), 'température', $message);
	$message = str_ireplace(array('lumière','lumiere','éclairage','eclairage', 'lampes'), 'lampe', $message);
	$message = str_ireplace(array('rideau','store'), 'volet', $message);
	$message = str_ireplace(array('fenetre'), 'fenêtre', $message);
	$message = str_ireplace(array('frigo', 'frigidaire'), 'réfrigérateur', $message);
	$message = str_ireplace(array('congelo', 'congélo', 'congelateur'), 'congélateur', $message);
	$message = str_ireplace(array('puissance consommée', 'puissance consommee'), 'puissance', $message);
	$message = str_ireplace(array('diana'), 'yanna', $message);
	$message = str_ireplace(array('œil'), 'oeil', $message);
	$message = str_ireplace(array('cap', 'direction'), 'direction', $message);

	// ------------------------- GESTION DES OBJETS AFFECTES D'OFFICE A LA LOCALISATION 'Salon' -----------------------
	if (strpos($message, 'salon') === false) {
		str_ireplace(array('entrée', 'entree', 'bureau', 'Salle à Manger', 'séjour', 'cuisine', 'couloir', 'booster', 'lave vaisselle', 'plaque induction', 'micro onde', 'informatique', 'retroprojecteur', 'réfrigérateur'), '', $message, $count);
		if ($count > 0) {
			$message = "$message du salon";
		}
	}

	// ------------------------- GESTION DES OBJETS AFFECTES D'OFFICE A LA LOCALISATION 'Rez-de-Chaussée' -----------------------
	if (strpos($message, 'Rez-de-Chaussée') === false) {
		str_ireplace(array('lave linge'), '', $message, $count);
		if ($count > 0) {
			$message = "$message du Rez-de-Chaussée";
		}
	}
	
	// ------------------------- GESTION DES OBJETS AFFECTES D'OFFICE A LA LOCALISATION 'Extérieur' -----------------------
	if (strpos($message, 'extérieur') === false) {
		str_ireplace(array('compresseur', 'piscine', 'direction', 'vitesse', 'rafale', 'pression'), '', $message, $count);
		if ($count > 0) {
			$message = "$message de l_ extérieur";
		}
	}
	mg::message('', ". Message nettoyé : $message");
	return $message;
}

/*********************************************************************************************************************/
/************************************* RECHERCHE ET LANCEMENT DE LA DEMANDE ******************************************/
/*********************************************************************************************************************/
function d_requete($r) {
	global $index;
	$values = array();
	$valSlider = $r['valSlider'];

	// Première Recherche commande
	$resultSql = e_requeteSQL1($r);

	// Si problème on lance la requète2
	if (count($resultSql) == 0 || count($resultSql) > 1) {
		$resultSql = f_requeteSQL2($r);
	}


	// CONTROLE UNICITE DE LA REQUETE
	if (count($resultSql) > 1) {
		g_setReponse("Votre demande à plus d'une réponse possible, veuillez être plus précis");
		mg::message('Log:/_debug', "Plus d'une réponse : " .$r['message']); // debug
		return -1;
	// CONTROLE ABOUTISSEMENT DE LA REQUETE
	} else	if (count($resultSql) == 0) {
		g_setReponse("Votre demande est introuvable, essayer une autre formulation");
		mg::message('Log:/_debug', "Introuvable : " .$r['message']); // debug
		return -1;
	}

	// COMMANDE ACTION
	if ($r['typeCmd'] == 'action') {
		$valInfo = getEtatId($resultSql[0]['eqType_name'], $resultSql[0]['eqLogic_id'], "({$r['motsResiduels']}).*({$r['objetComp']})", $subType, $unite);

		// Si rien à faire
		mg::message('', "$valInfo > 0 && $valSlider > 0 && $subType");
		if ( $valInfo == $valSlider || ($valInfo > 0 && $valSlider > 0 && $subType != 'numeric')) {
				g_setReponse($r['txtCmd']. " (...)est dèja " . libelleEtat($r['objet'], $valInfo, $subType, $unite) . ".");

		// Sinon lancement de la commande
		} else {
			mg::setCmd('#' . $resultSql[0]['cmd_id'] . '#', '', intval($valSlider));
			g_setReponse("c'est fait, ". trim($r['txtCmd']) . " (...)est maintenant à " . libelleEtat($r['objet'], $valSlider, $subType) . ".");
		}
	// COMMANDE INFO
	} else	if ($r['typeCmd'] == 'info') {
		$unite = $resultSql[0]['unite'];
		$subType = $resultSql[0]['subType'];
		$valInfo =	mg::getCmd('#' . $resultSql[0]['cmd_id'] . '#');
		g_setReponse($r['txtCmd']. " (...)est " . libelleEtat($r['objet'], $valInfo, $subType, $unite) . ".");
	}
}

/*********************************************************************************************************************/
/******************************* MAKE ET LANCEMENT DE LA REQUETE SUR LES VIRTUELS ************************************/
/*********************************************************************************************************************/
function e_requeteSQL1($r) {
	global $tabRegex, $index;
	$values = array();

	$sql = "
SELECT
-- ******** Req1 - $index - {$r['message']} ********
--
	object.name as `localisation`,
	eqLogic.eqType_name, eqLogic.name, eqLogic.id as `eqLogic_id`, eqLogic.tags, eqLogic.isEnable, eqLogic.comment,
	cmd.id as `cmd_id`, cmd.type as `cmd_type`, cmd.name as `cmd_name`, cmd.subType, cmd.value as `id_Etat`, cmd.isHistorized, cmd.unite
FROM `eqLogic`
	LEFT JOIN `cmd` ON eqLogic.id = cmd.eqLogic_id
	LEFT JOIN `object` ON object.id = eqLogic.object_id
WHERE
	eqLogic.isEnable = 1											-- FILTRAGE isEnabled 0
	AND lower(eqLogic.eqType_name) REGEXP lower('virtual')			-- FILTRAGE PROTOCOLE 1
	AND lower(object.name) = lower('{$r['localisation']}')			-- FILTRAGE LOCALISATION 2
	AND lower(cmd.type) = lower('{$r['typeCmd']}')					-- FILTRAGE TYPE DE COMMANDE 3
--
	AND lower(eqLogic.name) REGEXP lower('{$r['equipVirtuel']}') 	-- FILTRAGE EQUIPEMENTS VIRTUEL 4
--	
	 AND lower(cmd.name) REGEXP lower('{$r['objet']}') 				-- 5a
	 AND lower(cmd.name) REGEXP lower('{$r['objetComp']}') 			-- 5b
--	 AND lower(cmd.name) REGEXP lower('{$r['motsResiduels']}') 		-- 5c
--
LIMIT 5
";
	mg::messageT('', $sql);
	$resultSql = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	mg::message('', "Résultats de la Req1 : " . print_r($resultSql, true));

	// Recherche la bonne commande dans les résultats et remplace $resultSql[0] par elle, cela permet de gérer les commandes virtuelles unique sans autre précision. ex "Sirène" et non "Sirène Etat") ou celle redonnant la localisation pour les rendre unique ("Température extérieure")
	if (count($resultSql) > 1) {
		foreach($resultSql as $key => $result) {
			if (stripos($result['cmd_name'], $r['cmd']) !== false || stripos($result['cmd_name'], $r['localisation']) !== false) {
				mg::message('', "Ligne OK : $key - " . $result['cmd_name']);
				$result = array('0'=>$resultSql[$key]);
				mg::message('', "Résultats de la Req1 RETRAVAILLEE : " . print_r($result, true));
		$resultSql = $result;
		break;
			}
		}
	}
	
	return $resultSql;
}

/*********************************************************************************************************************/
/************************* MAKE ET LANCEMENT DE LA REQUETE SQL SUR LES PROTOCOLES PHYSIQUE ***************************/
/*********************************************************************************************************************/
function f_requeteSQL2($r) {
	global $tabRegex, $index;
	$values = array();

	$sql = "
SELECT
-- ******** Req2 - $index - {$r['message']} ********
--
	object.name as `localisation`,
	eqLogic.eqType_name, eqLogic.name, eqLogic.id as `eqLogic_id`, eqLogic.tags, eqLogic.isEnable, eqLogic.comment,
	cmd.id as `cmd_id`, cmd.type as `cmd_type`, cmd.name as `cmd_name`, cmd.subType, cmd.value as `id_Etat`, cmd.isHistorized, cmd.unite
FROM `eqLogic`
	LEFT JOIN `cmd` ON eqLogic.id = cmd.eqLogic_id
	LEFT JOIN `object` ON object.id = eqLogic.object_id
WHERE
	eqLogic.isEnable = 1											-- FILTRAGE isEnabled 0
AND lower(eqLogic.eqType_name) REGEXP lower('{$tabRegex['Protocoles']}') -- FILTRAGE PROTOCOLE 1
	AND lower(object.name) = lower('{$r['localisation']}')			-- FILTRAGE LOCALISATION 2
	AND lower(cmd.type) = lower('{$r['typeCmd']}')					-- FILTRAGE TYPE DE COMMANDE 3
--
	AND lower(eqLogic.name) REGEXP lower('{$r['objet']}') 			-- 4a
	AND lower(eqLogic.name) REGEXP lower('{$r['objetComp']}') 		-- 4b
	AND lower(eqLogic.name) REGEXP lower('{$r['motsResiduels']}') 	-- 4c 	

	AND lower(cmd.name) REGEXP lower('^({$r['cmd']})')				-- 5
--
LIMIT 5
";

	mg::messageT('', $sql);
	$resultSql = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	mg::message('', "Résultats de la Req2 : " . print_r($resultSql, true));
	return $resultSql;
}

/*********************************************************************************************************************/
/*************************************************** ENVOI DES REPONSES **********************************************/
/*********************************************************************************************************************/
function g_setReponse($message) {
global $telegram, $org, $expediteur, $index, $silencieux, $infReponse;

	// Repose des remplacements et nettoyage
	$message = str_replace('l_ ', 'l\'', $message);
	$message = str_replace('d_ ', 'd\'', $message);
	
	$message = str_replace('  ', ' ', $message);

	mg::setVar('interactReponse', $message);

	// TELEGRAM
	if ($org == 'TELEGRAM') {
		mg::setCmd($telegram, '', $message);

	// GOOGLECAST ???????????????????
	} elseif ($org == 'GOOGLECAST') {
		mg::GOOGLECAST('TTS', $message);

	// JPI_VOCAL
	} elseif ($org == 'JPI_INTERACTION' || ($org == 'JEEDOM'&& !$silencieux)) {
//		 mg::JPI('TTS', $message , 60);
//		 mg::GOOGLECAST('TTS', $message , -1);

	// JPI_SMS
	} elseif ($org == 'JPI_SMS') {
		mg::JPI('SMS', $message, $expediteur);
	}

	mg::messageT('', "! Envoi à $org de '$index - $message'");
}

/*********************************************************************************************************************/
/******************************************** TRANSPO TXT DES UNITES *************************************************/
/*********************************************************************************************************************/
function transpoUnite($unite='%') {
	$unite = trim($unite);
	if ($unite == '°C')			{ return '° Celsius'; }
	else if ($unite == 'Lux')	{ return 'Luxe'; }
	else if ($unite == 'A')		{ return 'Ampère'; }
	else if ($unite == 'W')		{ return 'Watt'; }
	else if ($unite == 'kWh')	{ return 'KiloWatt Heure'; }
	else if ($unite == 'V')		{ return 'Volt'; }
	else if ($unite == '°')		{ return 'Degré'; }
	else if ($unite == 'Km//h')	{ return 'Kilomètre heure'; }
	else if ($unite == 'hP')	{ return 'hecto pascal'; }
	else { return $unite; }
}

/*********************************************************************************************************************/
/*************************************************** TRANSPO LIBELLE ETAT ********************************************/
/*********************************************************************************************************************/
function libelleEtat($objet, $etat, $subType, $unite='') {

	$etat = str_replace('.', ',', $etat);
	$ret = "à $etat " . transpoUnite($unite);

	if ($subType == 'string') {
		if (strpos($etat, 'HG') !== false) { $ret = ' : Hors Gel'; }
		if (strpos($etat, 'Eco') !== false) { $ret = ' : Economique'; }
		if (strpos($etat, 'Confort') !== false) { $ret = ' : Confort'; }
		return $ret;
	}

	$etat = intval($etat); // Pour supprimer éventuel '%'
	 mg::message('', "_libEtat => objet : '$objet', etat : '$etat', subType : '$subType', unité: '$unite'");
	if (strpos($objet, 'lampe') !== false) {
		if ( $etat == 99) { $ret = 'allumée'; } elseif ( $etat == 0) { $ret = 'éteinte'; }

	} elseif (strpos($objet, 'porte') !== false || strpos($objet, 'fenêtre') !== false) {
		if ( $etat > 0) { $ret = 'ouverte'; } elseif ( $etat == 0) { $ret = 'fermée'; }

	} elseif (strpos($objet, 'volet') !== false) {
		if ( $etat == 99) { $ret = 'Ouvert'; } elseif ( $etat == 0) { $ret = 'Fermé'; }

	} else if ($subType == 'numeric') {
		if ( $etat == 99) { $ret = 'en route'; } elseif ( $etat == 0) { $ret = 'arrété'; }

	} else if ($subType == 'binary' || $subType == 'other') {
		if ( $etat > 0) { $ret = 'en route'; } elseif ( $etat == 0) { $ret = 'arrété'; }
	}

	return $ret;
}

/*********************************************************************************************************************/
/****************************************** RECHERCHE L'ETAT D'UN COMMANDE ACTION ************************************/
/*********************************************************************************************************************/
function getEtatId($typeCmd, $cmdID, $nomCmd, &$subType, &$unite) {
	$values = array();
	// Equipement virtuel
	if ($typeCmd == 'virtual') {
		$sql =	"SELECT id, subType, unite FROM `cmd`
				WHERE `eqLogic_id` = '$cmdID' AND lower(name) REGEXP lower('$nomCmd.*Etat')";
	// Equipement physique
	} else {
		$sql =	"SELECT id, subType, unite FROM `cmd`
				WHERE `eqLogic_id` = '$cmdID' AND lower(name) REGEXP lower('Etat')";
	}

	$resultSql = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	$valInfo = mg::getCmd($resultSql[0]['id']);
	$etatID = $resultSql[0]['id'];
	$subType = $resultSql[0]['subType'];
	$unite = $resultSql[0]['unite'];

	mg::message('', "$sql");
	mg::message('', print_r($resultSql, true));
	mg::message('', "getEtatId => cmdID : $cmdID - subType : $subType - etatID : $etatID - valInfo : $valInfo - unite - $unite");

	return $valInfo;
}

/*********************************************************************************************************************/
/************************************* RECHERCHE DE L'EQUIPEMENT VIRTUEL D'UNE ACTION ********************************/
/*********************************************************************************************************************/
function getEqVirtuel(&$r) {
	global $tabRegex;

	// Parcours de la liste des équipements virtuels
	$tabListeEquipementsVirtuels = explode('|', $tabRegex['ListeEquipementsVirtuels']);
	foreach($tabListeEquipementsVirtuels as $key => $equipVirtuel) {
		$regex = $tabRegex[$equipVirtuel];
		if ($r['equipVirtuel'] == '') {
			preg_match("/$regex/ui", $r['message'], $found);
			if (@iconv_strlen($found[0]) > 1) {
				$r['equipVirtuel'] = $equipVirtuel;
				$r['objet'] = trim($found[0]);
			}
		}
	}
}

/*********************************************************************************************************************/
/*************************************** RECHERCHE DE LA COMMANDE ACTION DANS L'ACTION *******************************/
/*********************************************************************************************************************/
function getCmdAction(&$r) {
	global $tabRegex;

$r['cmd'] = '';
	// SlIDER
	if (!$r['cmd']) {
		preg_match("/".$tabRegex['Slider']."/ui", $r['message'], $found);
		if (@iconv_strlen($found[0]) > 1) {
			$r['typeCmd'] = 'action';
			$r['cmd'] = 'Slider';
			$r['action'] = trim($found[0]);
		}
	}

	// ON
	if (!$r['cmd']) {
		preg_match("/".$tabRegex['On']."/ui", $r['message'], $found);
		if (@iconv_strlen($found[0]) > 1) {
			$r['typeCmd'] = 'action';
			$r['cmd'] = 'On';
			$r['action'] = trim($found[0]);
			$r['valSlider'] = '99 %';
		}
	}

	// OFF
	if (!$r['cmd']) {
		preg_match("/".$tabRegex['Off']."/ui", $r['message'], $found);
		if (@iconv_strlen($found[0]) > 1) {
			$r['typeCmd'] = 'action';
			$r['cmd'] = 'Off';
			$r['action'] = trim($found[0]);
			$r['valSlider'] = '0 %';
		}
	}

	// VALEUR SLIDER
	$regex = "([0-9]{1,3})(.)?%?";
	preg_match("/$regex/ui", $r['message'], $found);
	if (@iconv_strlen($found[0]) >= 1) {
		$r['typeCmd'] = 'action';
		$r['cmd'] = 'Slider';
		$r['valSlider']	= trim($found[0]);
	}
}

/*********************************************************************************************************************/
/*************************************** RECHERCHE D'UNE COMMANDE INFO DANS L'ACTION *********************************/
/*********************************************************************************************************************/
function getCmdInfo(&$r) {
	global $tabRegex;

	if (!$r['cmd']) {
		$regex = "(quel(le)?(s)\s?(est|sont)?)|((dis|donne).?(moi)?)";
		preg_match("/$regex/ui", $r['message'], $found);
		if (@iconv_strlen($found[0]) > 1) {
			$r['typeCmd'] = 'info';
			$r['action'] = trim($found[0]);
		}

		preg_match("/".$tabRegex['Info']."/ui", $r['message'], $found);
		if (@iconv_strlen($found[0]) > 1) {
			$r['typeCmd'] = 'info';
			$r['cmd'] = trim($found[0]);
		} else { $r['cmd'] = 'Etat'; }
		
		//if ($r['cmd'] == $r['objet']) {$r['cmd'] = 'etat'; }////////////////////////////////////////
		
		// exceptions de libellé pour l'état d'une commande
		if ($r['objet'] == 'porte') { $r['cmd'] = 'ouverture'; }
		if ($r['objet'] == 'fenêtre') { $r['cmd'] = 'ouverture'; }
	}
}

/*********************************************************************************************************************/
/***************************************** RECHERCHE DES LOCALISATIONS DANS L'ACTION *********************************/
/*********************************************************************************************************************/
function getLocalisations(&$r) {
	global $tabRegex;

	// Recherche localisation
	preg_match("/".$tabRegex['Localisation']."/ui", $r['message'], $found);
	if (@iconv_strlen($found[0]) > 1) {
		$r['localisation'] = trim($found[0]);
	}
}
/*********************************************************************************************************************/
/***************************************** RECHERCHE DE L'OBJET DANS L'ACTION ****************************************/
/*********************************************************************************************************************/
function getObjet(&$r) {
	global $tabRegex;

	preg_match("/".$tabRegex['Objets']."/ui", $r['message'], $found);
	if (@iconv_strlen($found[0]) > 1) {
		$r['objet'] = trim($found[0]);
	}

	// Recherche des Objets complémentaires
	preg_match("/".$tabRegex['ObjetsComp']."/ui", $r['message'], $found);
	if (@iconv_strlen($found[0]) > 1) {
		$r['objetComp'] = trim($found[0]);
	}
}

/*********************************************************************************************************************/
/********************************* SET DE LA LISTE DES REGEXP DES EQUIPEMENTS VIRTUELS ********************************/
/*********************************************************************************************************************/
function setRegexListeEquipVirtuel() {
	global $tabRegex;
	$values = array();

	// EXTRACTION DE LA LISTE DES EQUIPEMENTS VIRTUELS A EXPLOITER
	$sql = "
		SELECT name, object_id,	eqType_name, isEnable
		-- ******** Extraction Liste des Equipements Virtuels ********
		--
		FROM `eqLogic`
		--
		WHERE
		eqLogic.isEnable = 1
		AND lower(eqLogic.eqType_name) = lower('virtual')
		AND LEFT(eqLogic.name, 1) != '\*' and eqLogic.name != 'Résumé Global'
		
		GROUP BY eqLogic.name
		--
	";
	$resultSql = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);

	// Construction du regex
	$regex = '';
	for ($i=0; $i<count($resultSql); $i++) {
		$regex .= trim($resultSql[$i]['name']) . '|';
	}
	$tabRegex['ListeEquipementsVirtuels'] = trim($regex, '|');

	// DEPLACEMENT DE RESUME EN FIN DE LISTE
	$tabRegex['ListeEquipementsVirtuels'] = str_replace('|Résumé', '', $tabRegex['ListeEquipementsVirtuels']);
	$tabRegex['ListeEquipementsVirtuels'] .= '|Résumé';
}

/*********************************************************************************************************************/
/************************************** SET DES REGEXP DES EQUIPEMENTS VIRTUELS ***************************************/
/*********************************************************************************************************************/
function setRegexEquipVirtuel() {
	global $tabRegex;
	$values = array();
	$tabRegex['globalVirtuel'] = '';

	// ****************************************************************************************************************
	// Parcours de la liste des équipements virtuels
	$tabListeEquipementsVirtuels = explode('|', $tabRegex['ListeEquipementsVirtuels']);
	foreach($tabListeEquipementsVirtuels as $key => $nameEquipVirtuel) {
		// CALCUL DE LA REGEXP DE L'EQUIPEMENT VIRTUEL
		$sql = "
			SELECT
			-- ******** Extraction $nameEquipVirtuel ********
			--
			object.name as `localisation`,
			eqLogic.name, eqLogic.id as `eqLogic_id`, eqLogic.isEnable,
			cmd.id as `cmd_id`, cmd.name as `cmd_name`, LEFT(cmd.name, LOCATE(' ', concat( cmd.name, ' '))) as `typeCmd`
			FROM `eqLogic`
			LEFT JOIN `cmd` ON eqLogic.id = cmd.eqLogic_id
			LEFT JOIN `object` ON object.id = eqLogic.object_id
			--
			WHERE
			eqLogic.isEnable = 1
			AND lower(eqLogic.eqType_name) = lower('virtual')
			AND lower(eqLogic.name) = lower('$nameEquipVirtuel')
			 AND lower(cmd.name) != lower('Rafraichir')
			 
			 AND lower(cmd.name) NOT REGEXP lower('{$tabRegex['On']}')
			 AND lower(cmd.name) NOT REGEXP lower('{$tabRegex['Off']}')
			 AND lower(cmd.name) NOT REGEXP lower('{$tabRegex['Slider']}')
			--
			GROUP BY typeCmd
		";
		$resultSql2 = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);

		// Construction du regex
		$regex = '';
		for ($i=0; $i<count($resultSql2); $i++) {
			$regex .= trim($resultSql2[$i]['typeCmd']) . '|';
		}
		$tabRegex[$nameEquipVirtuel] = trim($regex, '|');
		$tabRegex['globalVirtuel'] .= trim($regex, '|');
	}
}

/*********************************************************************************************************************/
/******************************************* DU SET REGEXP DE LOCALISATION ********************************************/
/*********************************************************************************************************************/
function setRegexLocalisations() {
	global $tabRegex;
	$values = array();
	// Génération automatique du regex sur la liste des 'Objets Parent' des équipements
	$regexLocalisation = '';
	$sql = "SELECT name, isVisible FROM `object` where name NOT REGEXP 'Sys'";
	$result = DB::Prepare($sql, (array)$values, DB::FETCH_TYPE_ALL);
	for($i=0; $i<count($result); $i++) {
		if ($result[$i]['isVisible'] ==1) { $regexLocalisation .= $result[$i]['name'] .	 '|'; }
	}
	$tabRegex['Localisation'] = trim($regexLocalisation, '|');
}

/*********************************************************************************************************************/
/********************************************** DU SET REGEXP DES OBJETS **********************************************/
/*********************************************************************************************************************/
function setRegexObjets() {
	global $tabRegex;
	$globalVirtuel = $tabRegex['globalVirtuel'];

	$values = array();
	$regexObjets = '';
	$sql = "
		SELECT
			-- ******** Extraction des Objets ********
			--
			eqLogic.name, eqLogic.isEnable,
			LEFT(eqLogic.name, LOCATE(' ', concat( eqLogic.name, ' '))) as `Objet`
			FROM `eqLogic`
		--
		WHERE
			eqLogic.isEnable = 1
			AND lower(eqLogic.eqType_name) REGEXP lower('{$tabRegex['Protocoles']}')
			
			AND lower(eqLogic.name) NOT REGEXP lower('{$tabRegex['On']}')
			AND lower(eqLogic.name) NOT REGEXP lower('{$tabRegex['Off']}')
			AND lower(eqLogic.name) NOT REGEXP lower('{$tabRegex['Slider']}')
			AND lower(eqLogic.name) NOT REGEXP lower('$globalVirtuel')
 			AND lower(eqLogic.name) NOT REGEXP lower('{$tabRegex['Localisation']}')
		GROUP BY Objet
		--
	";
	
	$result = DB::Prepare($sql, (array)$values, DB::FETCH_TYPE_ALL);
	for($i=0; $i<count($result); $i++) {
		$regexObjets .= trim($result[$i]['Objet']) . '|';
	}
	$tabRegex['Objets'] = trim($regexObjets, '|');
	
	// OBJET COMPLEMENTAIRES
	$regexObjetsComp = '';
	$sql = "
		SELECT
			-- ******** Extraction des Objets ********
			--
			eqLogic.name, eqLogic.isEnable,
			SUBSTR(eqLogic.name, LOCATE(' ', eqLogic.name)+1) as `ObjetComp`

		FROM `eqLogic`
		--
		WHERE
			eqLogic.isEnable = 1
			AND IF(LOCATE(' ', eqLogic.name)>0, true, false)
			AND lower(eqLogic.eqType_name) REGEXP lower('{$tabRegex['Protocoles']}')
		
			AND lower(eqLogic.name) NOT REGEXP lower('{$tabRegex['On']}')
			AND lower(eqLogic.name) NOT REGEXP lower('{$tabRegex['Off']}')
			AND lower(eqLogic.name) NOT REGEXP lower('{$tabRegex['Slider']}')
			AND lower(eqLogic.name) NOT REGEXP lower('$globalVirtuel')
 			AND lower(eqLogic.name) NOT REGEXP lower('{$tabRegex['Localisation']}')
		--
		GROUP BY ObjetComp
	";
	
	$result = DB::Prepare($sql, (array)$values, DB::FETCH_TYPE_ALL);
	for($i=0; $i<count($result); $i++) {
		$regexObjetsComp .= trim($result[$i]['ObjetComp']) . '|';
	}
	$tabRegex['ObjetsComp'] = trim($regexObjetsComp, '|');
	
	
}

/*********************************************************************************************************************/
/******************************************** SET REGEXP DES COMMANDES INFO *******************************************/
/*********************************************************************************************************************/
function setRegexInfo() {
	global $tabRegex;

	$values = array();
	$regexInfo = '';
	$sql = "
		SELECT
					-- ******** Extraction des commandes Info des équipements physiques ********
			cmd.name, cmd.type, cmd.eqType

		FROM `cmd`

		WHERE
			cmd.type = 'info'
			AND lower(cmd.eqType) REGEXP lower('{$tabRegex['Protocoles']}')
			AND not LOCATE(' ', cmd.name)

			AND lower(cmd.name) NOT REGEXP lower('{$tabRegex['On']}')
			AND lower(cmd.name) NOT REGEXP lower('{$tabRegex['Off']}')
			AND lower(cmd.name) NOT REGEXP lower('{$tabRegex['Slider']}')

		GROUP BY cmd.name
	";
	$result = DB::Prepare($sql, (array)$values, DB::FETCH_TYPE_ALL);
	for($i=0; $i<count($result); $i++) {
		$regexInfo .= trim($result[$i]['name']) . '|';
	}
	$tabRegex['Info'] = trim($regexInfo, '|');
}

/*********************************************************************************************************************/
/******************************************** SET DE DONNEES POUR LE DEBUG *******************************************/
/*********************************************************************************************************************/
function setDebug() {
	$messages = array(
	"donne moi la température du salon et puis donne moi la température extérieure ensuite quel est la consommation de la lampe du bureau après donne moi la luminosité de de l'oeil de la cuisine et finalement donne moi l'humidité du salon monte le volet du bureau allume la lumière du séjour allume le booster",

	"quel est l'état de la lampe du séjour",
	"quelle est la puissance de la lampe du bureau",
	"donne moi la consommation de la lampe du séjour",
	"quelle est la température METAR extérieure",
	"quelle est la température extérieure",
	"quelle est l'humidité METAR extérieure",
	"donne moi la Pression de l'extérieur",
	"quel est la vitesse du vent",
	"donne moi le Cap du vent de l'extérieur",
	"donne moi la direction du vent de l'extérieur",
	"donne moi la température du congélo de l'extérieur",
	
	"donne moi la température du frigo du salon",
	"quelle est la température de la chambre",
	"température de l'oeil de la cuisine",
	"donne moi la luminosité de l'oeil de la cuisine",
	"donne moi l'humidité du salon",
	"quel est l'état de la porte du séjour",
	 "quel est l'état du booster",
	"quel est l'état de la lampe oeuf du salon",
	"donne moi l'état de la porte d'entrée",
	"quel est la consigne de chauffage de la chambre",
	"donne moi l'état de la sirène de la maison",
	
	"quelle est la puissance consommée par la lampe de la cuisine",
	"donne moi la puissance du lave linge",
	"donne moi la puissance du lave Vaisselle",
	"donne moi la puissance du compresseur de la piscine",
	"met la lampe couleur du salon à 0%",
	"allume le booster",
	"arrête le booster",
	"éteint la lampe du bureau",
	"règle la lampe du bureau a 50%",
	"allume la lampe du bureau",
	"baisse la lampe de la sam à 50%",
	
	"baisse la lampe de la sam à 50%",
	"met la lampe du séjour à 50%",
	"éteint la lampe couleur du salon",

//	"monte le volet du bureau",
//	"met le volet du bureau à 20%",
//	"allume la lampe générale du salon",
//	"éteint la lampe générale du salon",

	 );
	return $messages;
}

?>