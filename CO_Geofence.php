<?php
/**********************************************************************************************************************
Geofence - 123
Signale l'approche d'un user
**********************************************************************************************************************/
// SPECIAL DEBUG - REGENERE $tabGeofence via l'historique
// Infos, Commandes et Equipements :
//	$equipPresence, $equipMapPresence

// N° des scénarios :

//Variables :
	$tabUser = mg::getVar('tabUser');
	$tabGeofence = mg::getVar('tabGeofence');
	$latLng_Home = '43.35071300,5.20839500';
	$homeSSID = ' Livebox-MG';					// Valeur contenue dans le SSID de 'HOME'

	$destinatairesSOS = "Log, SMS:@MG, Mail:MG";// Destinataires du SOS
	$timingSOS = 900;						// Durée de pause 'normale' avant envoi d'un SOS

	$destinataires = 'Log, TTS:GOOGLECAST';	// Destinataire du message d'annonce de proximité
	$coeffDist = 0.9;						// Annonce de proximité faite si DistCouranteUser * $CoeffDist < OldDistUser

	$refresh = 120;							// Période de rafraichissement de la page HTML en seconde
	$marqueurSize = 20;						// Taille des icones de marquage trajet
	$layerDefaut = 'OpenStreetMap_France';	// Nom du layer par defaut OpenTopoMap, GeoportailFrance_maps, OpenStreetMap_France
	$epaisseur = 6;							// Epaisseur de la trace
	$colorNR = 'yellow';
	$colorMG = 'red';
	$colorPause = 'yellow';
	$colorVoiture = 'green';
	$colorEntrainement = 'red';
	$pauseMin = 2;							// Durée minimum de la Pause en mn pour l'afficher
	$coeffDeniveles = 0.5;					// Coeff à appliquer aux dénivelés


	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
	$fileExport = (getRootPath() . "$pathRef/util/geofence.html");

// Paramètres :

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

// ************************ SETINF DES VALUES GEOLOC DANS LES COMMANDES DE equipMapPresence ***************************
$userAppel = 'NR'; // Par defaut
$userActif = 'NR'; // Par defaut

if (mg::declencheur('SSID')) {
	$userAppel = str_replace('variable(', '', mg::declencheur('', 2));
	$userAppel = str_replace('_SSID)', '', $userAppel);
}
if (mg::declencheur('Position')) { $userAppel = str_replace('Tel-', '', mg::declencheur('', 2)); }

if (mg::declencheur('Position') || mg::declencheur('SSID')) {
    $GeofencepcBat = mg::getVar($userAppel.'_pcBat', '999');
    $GeofenceSSII = mg::getVar($userAppel.'_SSID', 'Pas de SSID');

    $PositionJeedomConnect = mg::getCmd("#[Sys_Comm][Tel-$userAppel][Position]#");
    $ActiviteJeedomConnect = mg::getCmd("#[Sys_Comm][Tel-$userAppel][Activité]#");
    if ($ActiviteJeedomConnect == 'still') { $ActiviteJeedomConnect = "I_$ActiviteJeedomConnect"; }
    elseif ($ActiviteJeedomConnect == 'on_foot') { $ActiviteJeedomConnect = "E_$ActiviteJeedomConnect"; }
    elseif ($ActiviteJeedomConnect == 'walking') { $ActiviteJeedomConnect = "E_$ActiviteJeedomConnect"; }
    elseif ($ActiviteJeedomConnect == 'running') { $ActiviteJeedomConnect = "E_$ActiviteJeedomConnect"; }
    elseif ($ActiviteJeedomConnect == 'on_bicycle') { $ActiviteJeedomConnect = "V_$ActiviteJeedomConnect"; }
    elseif ($ActiviteJeedomConnect == 'in_vehicle') { $ActiviteJeedomConnect = "V_$ActiviteJeedomConnect"; }
	mg::messageT('', "! User : $userAppel => Enregistrement d'un point");
	// modifier pour prendre en compte la date de l'info et enrgistrer le tout en BdD comme modif aux bonnes dates
    mg::setInf($equipMapPresence, $userAppel, "$PositionJeedomConnect, $GeofencepcBat, $GeofenceSSII, $ActiviteJeedomConnect");

	$userActif = $userAppel;
	if ((time() - mg::getVar('_GeoLastRun')) <= 3) { return; }
	mg::setVar('_GeoLastRun', time());
}

// ************************************************ PARCOURS DES USERS ************************************************
foreach ($tabUser as $user => $detailsUser) {
	$dateSQL = date('Y\-m\-d', time()); // Date du jour au format SQL
	//$dateSQL = '2021-04-24';
	$user = str_replace('Tel-', '', $user);
	if (!$userAppel) { $userAppel = $user; }
	$distanceMax = floatval($detailsUser['geo']);
	if ($distanceMax <= 0) { continue; }

	$idUser = mg::toID($equipMapPresence, $user);
	$userPresent = mg::getCmd($equipPresence, $user);

	if (!$userPresent || $tabGeofence[$user]['debTime'] > 0) { $userActif = $user; }

	if (!$userPresent) { $tabGeofence[$user]['dateActive'] = $dateSQL; }
//	else { $dateSQL = isset($tabGeofence[$user]['dateActive']) ? $tabGeofence[$user]['dateActive'] : $dateSQL; }
	else { $dateSQL = @$tabGeofence[$user]['dateActive']; }

	makeLatLng($tabGeofence, $user, $dateSQL, $idUser, $pauseMin, $coeffDeniveles, $destinatairesSOS, $timingSOS);

	$nbLignes = count(explode(']', $tabGeofence[$user]['latlngs']));
	mg::messageT('', "! User : $user / actif : $userActif / Présent : $userPresent - NbLignes : $nbLignes - debTime : " . date('d\/m \à H\hi\m\n', $tabGeofence[$user]['debTime']) . " - Cloture : " . date('d\/m \à H\hi\m\n', $tabGeofence[$user]['cloture'])." - DateSQL : $dateSQL");

	// **************************************************** USER ACTIF ************************************************
	if ($userAppel == $user) {
		$value = mg::getCmd($equipMapPresence, $user);
		$value_ = explode(',', $value);
		$latLng_Home_ = explode(',', $latLng_Home);
		$dist = mg::getDistance($latLng_Home_[0], $latLng_Home_[1], $value_[0], $value_[1], 'k');

		// ******************************************** MAJ WIDGET GOOGLE MAP *****************************************
		$tmp = mg::getParamWidget($idUser, '');
		$tmp['from'] = $value_[0] . ', ' . $value_[1];
		mg::setParamWidget($idUser, '', $tmp);
		$tmp['to'] = $latLng_Home_[0] . ', ' . $latLng_Home_[1];
		mg::setParamWidget($idUser, '', $tmp);

		 // ********** Si message user avec SSID à Home on positionne la distance à -1 pour Alarme Présence et on saute *******
		$SSID = trim($value_[4]);
		if (strpos(" $SSID", $homeSSID) !== false || $dist < 0.15 || $userPresent > 0) {
			mg::setVar("dist_Tel-$user", -1);
			mg::unsetVar("_OldDist_$user");
			continue;
		}

		// **************************************** SIGNALEMENT PROXIMITE *********************************************
		mg::setVar("dist_Tel-$user", $dist);
/*		if ($dist < 0.15 || $userPresent > 0) {
			mg::unsetVar("_OldDist_$user");
			continue;
		}*/

		mg::messageT('', "! Signalement de proximité de $user à $dist km");
		$oldDist = mg::getVar("_OldDist_$user", $dist);
		if (abs($dist-$oldDist) > $distanceMax && $dist < $oldDist * $coeffDist && $dist > $distanceMax) {
			$distTxt = str_replace('.', ',', round($dist,1));
			mg::Message($destinataires, "'$user' en approche à $distTxt kilomètres.");
			mg::setVar("_OldDist_$user", $dist);
		} else {
			mg::setVar("_OldDist_$user", max($dist, $oldDist));
		}

		// ****************************************** ENVOIS D'UN SOS MANUEL ******************************************
		if (trim($value_[5]) == 'SOS') {
			mg::message($destinatairesSOS, "SOS MANUEL de $user, Coordonnées (" . $value_[0] . ', ' . $value_[1] . "). VOIR :  https://georgein.dns2.jeedom.com/mg/util/geofence.html");
		}

	}
}
	//	******************************************* ECRITURE DU FICHIER HTML **********************************************
		mg::messageT('', "! GENERATION HTML DE GEOFENCE");
		file_put_contents($fileExport, HTML($tabGeofence, $userActif, $latLng_Home, $refresh, $layerDefaut, $marqueurSize, $pauseMin, $epaisseur, $colorMG, $colorNR, $colorPause, $colorEntrainement, $colorVoiture, $pathRef));
		mg::setVar('tabGeofence', $tabGeofence);
		mg::message('', print_r($tabGeofence, true));

// ********************************************************************************************************************
// ********************************************************************************************************************
// ********************************************************************************************************************
function HTML($tabGeofence, $userActif, $latLng_Home, $refresh, $layerDefaut, $marqueurSize, $pauseMin, $epaisseur, $colorMG, $colorNR, $colorPause, $colorEntrainement, $colorVoiture, $pathRef) {

$tailleBoutons = '96px';
// Page HTML
$geofence = "
<!DOCTYPE html>
<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"fr\">
	<head>
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\" />
		<link rel=\"stylesheet\" href=\"$pathRef/ressources/leaflet/leaflet.css\" />
		<script src=\"$pathRef/ressources/leaflet/leaflet.js\"></script>
		<link rel=\"stylesheet\" href=\"$pathRef/ressources/leaflet/easy-button.css\" />
		<script src=\"$pathRef/ressources/leaflet/easy-button.js\"></script>
		<META HTTP-EQUIV='refresh' CONTENT='$refresh; URL=$pathRef/util/geofence.html'>
	</head>

	<style>
		html, body {
			height: 100%
		}
		.leaflet-marker-icon.leaflet-interactive {
			border-radius: 50% /* Mise cercle des icones */
		}

		.leaflet-bar button, .leaflet-bar button:hover, .leaflet-bar a, .leaflet-bar a:hover/*, .leaflet-touch*/, .leaflet-control-layers-toggle {
			width: $tailleBoutons!important; /* Largeur bouton image de zoom */
			height: $tailleBoutons!important; /* Hauteur bouton image de zoom */
			line-height: $tailleBoutons;
		}

		.leaflet-control-zoom-in, .leaflet-control-zoom-out {
			font: bold $tailleBoutons 'Lucida Console', Monaco, monospace;
		}


		.leaflet-bar, .leaflet-bar button:first-of-type, .leaflet-bar button:last-of-type, .leaflet-bar a:first-child, .leaflet-bar a:last-child {
		  border-radius: 50%;
		}

		.leaflet-bar {
			box-shadow: 0 15px 15px rgba(0,0,0,0.65);
		}

	.leaflet-popup-content {
		margin: 5px 10px;
		line-height: 1.4;
		font-size: 22px!important;
		width:600px!important;
		}

	.leaflet-tooltip {
		font-size: 22px;
	}

	.bt-reload {
		position: relative;
		text-align: center;
		background-color: red!important;
		color: white;
		margin-left: 50%;
		margin-top: 20px;

	}

	</style>

		<button class='bt-reload' value='Reload' id='reload' onclick='reload();' title='Recharge la page.'>--- Reload ---</button>
	<body onload=\"initialize()\">
		<div id=\"map\" style=\"width:100%; height:100%\"></div>
	</body>
</html>

<script type=\"text/javascript\">

function reload() { document.location.reload(); }

function initialize() {

	var latLng_Home = L.latLng($latLng_Home);
	var map = L.map('map').setView(latLng_Home, 15	);
	L.control.scale().addTo(map); // Pose de l'échelle
	// Options de la polyline : https://leafletjs.com/reference-1.6.0.html#path

// ********************************************* MARQUEUR POINT DE TRACE **********************************************
	var iconEntrainement = new L.Icon({
	  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-$colorEntrainement.png',
	  iconSize: [$marqueurSize, $marqueurSize],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
	  popupAnchor: [1, -1.2*$marqueurSize],
	});

	var iconVoiture = new L.Icon({
	  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-$colorVoiture.png',
	  iconSize: [$marqueurSize, $marqueurSize],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
	  popupAnchor: [1, -1.2*$marqueurSize],
	});

	var iconPause = new L.Icon({
	  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-$colorPause.png',
	  iconSize: [$marqueurSize, $marqueurSize],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
	  popupAnchor: [1, -1.2*$marqueurSize],
	});

	var iconPauseLongue = new L.Icon({
	  iconUrl: \"$pathRef/img/img_Binaire/presences/sleepingEmoji.png\",
	  iconSize: [$marqueurSize*2, $marqueurSize*2],
	  iconAnchor: [$marqueurSize/2, $marqueurSize/2],
//	  shadowSize: [41, 41]
	  popupAnchor: [1, -1.2*$marqueurSize],
	});


/*	L.marker(latLng_Home, {icon: iconEntrainement}).addTo(map)
	//	.bindTooltip(\"Maison Ensues\", {permanent: true, direction: 'bottom'}); // Affichage permanent
		.bindPopup('Maison Ensues'); // Affichage click*/
";

$polylineAll = '';
	$polylineAll .= "polyline$userActif,";
$numUser = 0;
foreach ($tabGeofence as $user => $detailsUser) {
	$nbLignes = (isset($tabGeofence[$user]['latlngs'])) ? count(explode(']', $tabGeofence[$user]['latlngs'])) : 0;
	if ($nbLignes <4) { continue; } // Rien à afficher pour le user

	$latlngs = $detailsUser['latlngs'];
	$complements = $detailsUser['complements'];

	$debTime = intval($detailsUser['debTime']);
	$lastTime = intval($detailsUser['lastTime']);
	$lastPcBatterie = intval($detailsUser['lastPcBatterie']);
	$lastSSID = $detailsUser['lastSSID'];

	$distanceTotale = round($detailsUser['distanceTotale'], 1);
	$denivelePlus = round($detailsUser['denivelePlus'], 0);
	$deniveleMoins = round($detailsUser['deniveleMoins'], 0);
	$cloture = intval(($detailsUser['cloture'] == 0 ? $detailsUser['lastTime'] : $detailsUser['cloture']));
	$vitesseMoyenneTotale = round($detailsUser['vitesseMoyenneTotale'], 1);
	$vitesseMoyenneMouvement = round($detailsUser['vitesseMoyenneMouvement'], 1);

	$debTimeStr = date('d/m \&#224;\ H:i', $debTime);
	$lastTimeStr = date('d/m \&#224;\ H:i', $lastTime);
	$clotureStr = date('d/m \&#224;\ H:i', $cloture);
	$dureeTotaleStr = gmdate('H\h\ i', intval($detailsUser['dureeTotale']));
	$dureeMouvementStr = gmdate('H\h\ i', intval($detailsUser['dureeMouvement']));
	$sommePauseStr = gmdate('H\m\n', intval($detailsUser['sommePause']*60*60));

	$polylineAll .= "polyline$user,";
	$imgBouton = "$user.png";
	$colorUser = $user == 'MG' ? $colorMG : $colorNR;
	$numUser++;

// ********************************************************************************************************************
// ********************************************************* DEBUT USER ***********************************************
// ********************************************************************************************************************
$geofence .=
	"
	// *********************************************** DEBUT DE LA TRACE $user ****************************************
	var latlngs = [$latlngs];
	var complements = [$complements];
	var dureePause = 0;
console.log('************'+latlngs.length); //////////////////////////////////////////////////////////

	var polyline$user = L.polyline(latlngs, {color:'$colorUser', weight:'$epaisseur' }).addTo(map);

	var iconUser = new L.icon({
		iconUrl: \"$pathRef/img/img_Binaire/presences/$user.png\",
		shadowUrl:	  'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
		iconSize:	  [48, 48], // taille de l'icone
		iconAnchor:	  [24, 48*$numUser-48], // point de l'icone qui correspondra à la position du marker
		shadowSize:	  [96, 96], // taille de l'ombre
		shadowAnchor: [48, 96], // idem pour l'ombre
		popupAnchor:  [-3, 42 -48*$numUser] // point depuis lequel la popup doit s'ouvrir relativement à l'iconAnchor
	});

	// ********************************************* MARQUEUR SUR LA TRACE ********************************************
	var sommeEcarts = 0;

	for ( var i=1; i < latlngs.length-1; ++i ) {
		var time = latlngs[i][2]*1000;
		// Conversion date en string
		var date = new Date(time);
		annee = date.getFullYear();
		moi = date.getMonth();
	   mois = new Array('Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre');
		j = date.getDate();
		jour = date.getDay();
		jours = new Array('Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi');
		h = date.getHours();
		if(h<10) { h = '0'+h; }
		m = date.getMinutes();
		if(m<10) { m = '0'+m; }
		s = date.getSeconds();
		if(s<10) { s = '0'+s; }
		dateString = j+' '+mois[moi] +' '+annee + ' &#224; ' + h + ':' + m + ':' + s;
		// Fin de la conversion

		var coordonnee = latlngs[latlngs.length-1].toString();
		var	 latLng = coordonnee.split(',');
		latLng_ = L.latLng(latlngs[latlngs.length-1]);
		var latitude = Math.round(latLng[0]*10000000000)/10000000000;
		var longitude = Math.round(latLng[1]*10000000000)/10000000000;

		var ecart = parseFloat(complements[i][0]);
		var altitude = parseInt(complements[i][2]);
		var activite = complements[i][3];
		var pcBatterie = parseInt(complements[i][4]);
		var dureePause = parseInt(complements[i-1][5]);
		var deltaI_Pause = parseInt(complements[i-1][6]);

		// Marqueur VOITURE
		if (activite == 'V') {
			L.marker( latlngs[i], {icon: iconVoiture} ).bindTooltip('$user : '	+ dateString + ' - Bat. ' + pcBatterie + ' %').addTo( map );
		}

		// Marqueur ENTRAINEMENT
		if (activite == 'E' || (activite == 'I' && $debTime > 0)) {
			L.marker( latlngs[i], {icon: iconEntrainement} ).bindTooltip( '<center> $user : ' + dateString + ' - '
				+ ecart + ' km - Alt ' + altitude + ' m - Bat. ' + pcBatterie + ' %'
				+ '<br>Coordonn&#232;es : (' + latitude + ' , ' + longitude + ')').addTo( map )
		}

		// Marqueur de PAUSE LONGUE, en fin de pause on affiche l'icone
		if (dureePause > $pauseMin ) {
			L.marker( latlngs[i-deltaI_Pause], {icon: iconPauseLongue} ).bindTooltip('$user : ' + dateString + ' - PAUSE de ' + Math.round(dureePause) + ' mn - Alt ' + altitude + ' m').addTo( map );
		}

		// Marqueur de PAUSE PONCTUELLE
		else if (activite == 'I' /*&& dureePause > 0 && dureePause <= $pauseMin*/) {
			L.marker( latlngs[i], {icon: iconPause} ).bindTooltip('$user : ' + dateString + ' - PAUSE de ' + Math.round(dureePause) + ' mn - Alt ' + altitude + ' m').addTo( map );
		}
	}

	// Icone du User sur la carte
	var message = '';
		if ($debTime != 0) {
/*		message = ' <center>********************* ENTRAINEMENT $user *********************'
		+ '<br>du $debTimeStr au $clotureStr'
		+ '<br>Dur&eacute;e $dureeTotaleStr, en mouvement $dureeMouvementStr, ($sommePauseStr mn de pause)'
		+ '<br>Dist ' + Math.round($distanceTotale*100)/100 + ' km - Vitesse $vitesseMoyenneTotale / $vitesseMoyenneMouvement km/h'
		+ '<br> -------------------------------------------------------------------------------<br>';*/
		message = ' <center>********************* ENTRAINEMENT $user *********************'
		+ '<br>du $debTimeStr au $clotureStr'
		+ '<br>Dur&eacute;e $dureeTotaleStr, en mouvement $dureeMouvementStr, ($sommePauseStr de pause)'
		+ '<br>Dist ' + Math.round($distanceTotale*100)/100 + ' km - d&eacute;nivel&eacute;s +' + $denivelePlus + ' m / ' + $deniveleMoins + ' m - Vitesse		$vitesseMoyenneTotale / $vitesseMoyenneMouvement km/h'
		+ '<br> -------------------------------------------------------------------------------<br>';
		}
		message =	message + '<center>$user : $lastTimeStr : Batterie $lastPcBatterie % - $lastSSID'
					+'<br> Coordonn&#232;es : (' + latitude + ' , ' + longitude + ')';

	L.marker(latLng_, {icon: iconUser}).addTo(map).bindTooltip( message ).openPopup(); // POPUP : bindPopup

	// ******************************************** BOUTONS de zoom $user *********************************************
		L . easyButton ( '<img src = \"$pathRef/img/img_Binaire/presences/$imgBouton\">' ,  function ( btn , map ) {
			var	 $user	=  L.latLng(latlngs[latlngs.length-1]);
			var group = new L.featureGroup([polyline$user]);
			map.fitBounds(group.getBounds());
		} ) . addTo ( map ) ;
	// ********************************************* FIN DE LA TRACE $user ********************************************
	";
}

// ********************************************************************************************************************
// ********************************************************** FIN USER ************************************************
// ********************************************************************************************************************
$geofence .= "

	// ********************************************* BOUTONS de zoom All **********************************************
	L . easyButton ( '<img src = \"$pathRef/img/img_Binaire/presences/monde.png\">' , function ( btn , map ) {
	var group = new L.featureGroup([$polylineAll]);
	map.fitBounds(group.getBounds());
} ) . addTo ( map ) ;

	// ************************************** ZOOM AU CHARGEMENT SUR UserActif ***************************************
	var group = new L.featureGroup([polyline$userActif]);
	map.fitBounds(group.getBounds());

	// *************************************** POSE DU REPERE HOME (REGION RONDE) *************************************
	var influence = L.circle(latLng_Home, 100, {
		'color': 'red',
		'fill': true,
		'fillColor': 'red',
		'fillOpacity': 0.2,
	}).addTo(map);

	// ****************************************************************************************************************
	// ****************************************************************************************************************
	// ***************** LISTE DES LAYERS SUR : http://leaflet-extras.github.io/leaflet-providers/preview/ ****************
	// ***************** SITE DE LEAFLET : https://leafletjs.com/ *****************

	// **************************************** DEFINITIONS DES LAYERS PROPOSES ***************************************

var OpenStreetMap_France = L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
	maxZoom: 20,
	attribution: '&copy; Openstreetmap France | &copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors'
});

	var osmLayer = L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
		attribution: '© OpenStreetMap contributors',
		maxZoom: 19,
   });

	var OpenTopoMap = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
		maxZoom: 17,
		attribution: 'Map data: &copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors, <a href=\"http://viewfinderpanoramas.org\">SRTM</a> | Map style: &copy; <a href=\"https://opentopomap.org\">OpenTopoMap</a> (<a href=\"https://creativecommons.org/licenses/by-sa/3.0/\">CC-BY-SA</a>)'
	});

	var Esri_WorldImagery = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
		attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
		minZoom: 0,
		maxZoom: 19,
	});

	var GeoportailFrance_ignMaps = L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: '<a target=\"_blank\" href=\"https://www.geoportail.gouv.fr/\">Geoportail France</a>',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 18,
		apikey: 'choisirgeoportail',
		format: 'image/jpeg',
		style: 'normal'
	});

	var GeoportailFrance_parcels = L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=CADASTRALPARCELS.PARCELS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: '<a target=\"_blank\" href=\"https://www.geoportail.gouv.fr/\">Geoportail France</a>',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 20,
		apikey: 'choisirgeoportail',
		format: 'image/png',
		style: 'bdparcellaire'
	});

	var GeoportailFrance_maps = L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=GEOGRAPHICALGRIDSYSTEMS.MAPS.SCAN-EXPRESS.STANDARD&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: '<a target=\"_blank\" href=\"https://www.geoportail.gouv.fr/\">Geoportail France</a>',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 18,
		apikey: 'choisirgeoportail',
		format: 'image/jpeg',
		style: 'normal'
	});

	var GeoportailFrance_orthos = L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: '<a target=\"_blank\" href=\"https://www.geoportail.gouv.fr/\">Geoportail France</a>',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 19,
		apikey: 'choisirgeoportail',
		format: 'image/jpeg',
		style: 'normal'
	});
/********** FIN INIT DES LAYERS **********/

// ******************************************* AFFICHAGE DU CHOIX DES LAYERS ******************************************
	map.addLayer($layerDefaut); // Le layer par dÃ©faut
	map.addControl(new L.Control.Layers( {

		'OpenStreetMap_France': OpenStreetMap_France,
		'GeoportailFrance_orthos': GeoportailFrance_orthos,

		'OpenStreetMap': osmLayer,
		'OpenTopoMap': OpenTopoMap,
		'Esri_WorldImagery': Esri_WorldImagery,
		'GeoportailFrance_ignMaps': GeoportailFrance_ignMaps,
		'GeoportailFrance_parcels': GeoportailFrance_parcels,
		'GeoportailFrance_maps': GeoportailFrance_maps,
		}, {})
	);
	// ************************************ FIN DE L'AFFICHAGE DU CHOIX DES LAYERS ************************************

}
</script>

";

return $geofence;
}

/*********************************************************************************************************************/
/************************************************* Extrait les LatLong de l'historiques ******************************/
/*********************************************************************************************************************/
function makeLatLng(&$tabGeofence, $user, $dateSQL, $id, $pauseMin, $coeffDeniveles, $destinatairesSOS, $timingSOS) {
	global $scenario;
	$values = array();
	$tabGeofence[$user]['latlngs'] = '';
	$tabGeofence[$user]['complements'] = '';
	$tabGeofence[$user]['dureeMouvement'] = 0;
	$tabGeofence[$user]['vitesseMoyenneTotale'] = 0;
	$tabGeofence[$user]['vitesseMoyenneMouvement'] = 0;
	$tabGeofence[$user]['debTime'] = 0;
	$tabGeofence[$user]['cloture'] = 0;
	$tabGeofence[$user]['distanceTotale'] = 0;
	$tabGeofence[$user]['dureeTotale'] = 0;
	$tabGeofence[$user]['denivelePlus'] = 0;
	$tabGeofence[$user]['deniveleMoins'] = 0;
	$tabGeofence[$user]['sommePause'] = 0;
	$deltaI_Pause = 0;

	$complements = '';

	$timeCourante = 0;
	$dureePause = 0;
	$ecart = 0;
	$dureeEcart = 0;
	$altitude = 0;
	$oldAltitude = 0;
	$ecartAltitude = 0;
	$debPause = 0;
	$inhibe = 0;

	$id = trim($id, '#');
	$sql = "
		SELECT value, datetime
		FROM `history`
		WHERE `cmd_id` = '$id' AND `datetime` LIKE '%$dateSQL%'
		ORDER BY `datetime` ASC
		-- LIMIT 1000
	";
	mg::message('', $sql);
	$result = DB::Prepare($sql, (array)$values, DB::FETCH_TYPE_ALL);
	$tabDetails = calculLigne($result);

//	for ($i=1; $i<count($result)-3; $i++) {
	for ($i=1; $i<count($tabDetails)-1; $i++) {
		// ******************************** Init des variables via $tabDetails calculé ********************************
		$timeCourante_M1 = strtotime($result[$i-1]['datetime']);
		$timeCourante = strtotime($result[$i]['datetime']);
		$timeCourante_P1 = strtotime($result[$i+1]['datetime']);

		$activite = $tabDetails[$i]['activite'];
		$activite_P1 = $tabDetails[$i+1]['activite'];

		$latlng = $tabDetails[$i]['latlng'];
		$latitude = $tabDetails[$i]['latitude'];
		$longitude = $tabDetails[$i]['longitude'];
		$altitude = $tabDetails[$i]['altitude'];
		$oldAltitude = $tabDetails[$i-1]['altitude'];
		$pcBatterie = $tabDetails[$i]['pcBatterie'];
		$SSID = trim($tabDetails[$i]['SSID']);
		$ecart = $tabDetails[$i]['ecart'];
		$dureeEcart = $tabDetails[$i]['dureeEcart'];
		$vitesseEcart = $tabDetails[$i]['vitesseEcart'];
		$inhibe = $tabDetails[$i]['inhibe'];

		// ********************************************** ENTRAINEMENTS ***********************************************
		if ($SSID != 'Pas de SSID' && $tabGeofence[$user]['debTime'] <= 0) { $tabGeofence[$user]['SSID_Org'] = $SSID; }
		if ($activite == 'I'  || $activite == 'E') {
			// Demarrage Entrainement
			if ($SSID == 'Pas de SSID' && $activite == 'E' && $tabGeofence[$user]['debTime'] <= 0) {
				$tabGeofence[$user]['debTime'] = $timeCourante;
				$tabGeofence[$user]['cloture'] = 0;
				$tabGeofence[$user]['distanceTotale'] = 0;
				$tabGeofence[$user]['sommePause'] = 0;
				$tabGeofence[$user]['denivelePlus'] = 0;
				$tabGeofence[$user]['deniveleMoins'] = 0;
			}
				// ENTRAINEMENT EN COURS
			if (!$inhibe && $tabGeofence[$user]['debTime'] > 0 && $tabGeofence[$user]['cloture'] == 0) {
				$tabGeofence[$user]['distanceTotale'] += $ecart;

				// Traitement delta altitude
				$ecartAltitude = round($altitude - $oldAltitude, 2);
				if (abs($ecartAltitude) < 50) {
					if ($ecartAltitude > 0) { $tabGeofence[$user]['denivelePlus'] += ($ecartAltitude * $coeffDeniveles); }
					if ($ecartAltitude < 0) { $tabGeofence[$user]['deniveleMoins'] += ($ecartAltitude * $coeffDeniveles); }
				}

				// ********** GESTION DES PAUSES **********
				if ($activite == 'I') {
					// Début de pause
					if ($debPause == 0) {
						$debPause = $timeCourante_M1;
						$iDeb = $i;
					// Pause en cours
					} else {
						// *************** ENVOIS D'UN SOS AUTOMATIQUE ***************
						if ($dureePause > $timingSOS / 60 && (time() - $timeCourante) < 60*60) {
							mg::message($destinatairesSOS, "SOS AUTOMATIQUE de $user, Aucun mouvement depuis plus de " . $timingSOS/60 . " mn, Coordonnées ($latlng). VOIR :  https://georgein.dns2.jeedom.com/mg/util/geofence.html");
						}
					}

				// Fin de pause
				} elseif ($debPause > 0) {
						$dureePause = round(($timeCourante_P1 - $debPause)/60, 1);
					$tabGeofence[$user]['sommePause'] += $dureePause;
					$deltaI_Pause=$i-$iDeb+1;
					$debPause = 0;
				} else {
					$dureePause = 0;
					$deltaI_Pause = 0;
				}
			} // ********** FIN GESTION DES PAUSES **********

		} // ******************************************** FIN ENTRAINEMENTS *********************************************
		if ($tabGeofence[$user]['debTime'] > 0 && $tabGeofence[$user]['cloture'] == 0) {
			if (	$SSID != 'Pas de SSID'
					 || strpos($tabGeofence[$user]['SSID_Org'], $SSID) !== false
					 || strpos($SSID, $tabGeofence[$user]['SSID_Org']) !== false
					 || ($activite == 'V' && $activite_P1 == 'V') )
				{
				$tabGeofence[$user]['cloture'] = $timeCourante_M1;
			}
		}

		// ********** SUPPRESSION ENTRAINEMENT TROP COURT (< 1 km ou 15 mn) **********
		if ($tabGeofence[$user]['cloture'] > 0 && $timeCourante +300 > $tabGeofence[$user]['cloture'] && ($tabGeofence[$user]['distanceTotale'] < 1)) {
			mg::message('', "************* Suppression entrainement trop court de $user : Distance : " . $tabGeofence[$user]['distanceTotale'].' - Durée : '.date('H:m:s', $tabGeofence[$user]['dureeTotale']));
			$tabGeofence[$user]['debTime'] = 0;
			$tabGeofence[$user]['cloture'] = 0;
			$tabGeofence[$user]['dureeTotale'] = 0;
			$tabGeofence[$user]['sommePause'] = 0;
			$tabGeofence[$user]['$dureePause'] = 0;
			$tabGeofence[$user]['distanceTotale'] = 0;
			$tabGeofence[$user]['denivelePlus'] = 0;
			$tabGeofence[$user]['deniveleMoins'] = 0;
		}

		// Enregistrement pour affichage
		$message = "$i $activite $user => ".date('d-m H:i:s', $timeCourante) ." - $SSID - $ecart / ".$tabGeofence[$user]['distanceTotale']." km - $dureeEcart sec - $vitesseEcart km/h - Pause : $dureePause / ".$tabGeofence[$user]['sommePause']." mn -  => $altitude - *$ecartAltitude* - ".$tabGeofence[$user]['denivelePlus'].' - '.$tabGeofence[$user]['deniveleMoins'];

		if (!$inhibe) {
			$tabGeofence[$user]['latlngs'] .= "[$latlng, $timeCourante],";
			$tabGeofence[$user]['complements'] .= "[".round($tabGeofence[$user]['distanceTotale'], 2).", $dureeEcart, $altitude, '$activite', $pcBatterie, $dureePause, $deltaI_Pause],";
			if ($scenario->getConfiguration('logmode') == 'realtime') { mg::message('', "  $message"); }
		} elseif ($tabDetails[$i]['activite'] != 'I' && $tabDetails[$i]['ecart'] != 0) {
			/*if ($scenario->getConfiguration('logmode') == 'realtime')*/ { mg::message('', "* $message"); }
		}
	} // fin for

	// *********************************** CALCUL DE LA SYNTHESE DE L'ENTRAINEMENT ************************************
	if ($tabGeofence[$user]['debTime'] != 0) {
	$tabGeofence[$user]['dureeTotale'] = (($tabGeofence[$user]['cloture'] != 0) ? $tabGeofence[$user]['cloture'] : $timeCourante_M1) - $tabGeofence[$user]['debTime'];

		$tabGeofence[$user]['dureeMouvement'] = $tabGeofence[$user]['dureeTotale'] - $tabGeofence[$user]['sommePause']*60;
		if ($tabGeofence[$user]['distanceTotale'] == 0 || $tabGeofence[$user]['dureeMouvement'] == 0) {
			$tabGeofence[$user]['vitesseMoyenneTotale'] = 0;
			$tabGeofence[$user]['vitesseMoyenneMouvement'] = 0;
		}
		else {
			$tabGeofence[$user]['vitesseMoyenneTotale'] = $tabGeofence[$user]['distanceTotale'] / $tabGeofence[$user]['dureeTotale'] * 3600;
			$tabGeofence[$user]['vitesseMoyenneMouvement'] = $tabGeofence[$user]['distanceTotale'] / $tabGeofence[$user]['dureeMouvement'] * 3600;
		}
	}

	// Calcul des lastValues
	$tabGeofence[$user]['lastTime'] = (isset($tabDetails[$i]['datetime']) ? $tabDetails[$i]['datetime'] : 0);
	$tabGeofence[$user]['lastPcBatterie'] = (isset($tabDetails[$i]['pcBatterie']) ? $tabDetails[$i]['pcBatterie'] : 0);
	$tabGeofence[$user]['lastSSID'] = (isset($tabDetails[$i]['SSID']) ? $tabDetails[$i]['SSID'] : '');
}

/*********************************************************************************************************************/
/************************************************ Init/Calcul $tabDetails des lignes *********************************/
/*********************************************************************************************************************/
function calculLigne($result) {
	$tabDetails = array();
	for($i=0; $i<count($result); $i++) {
		$tabDetails[$i]['datetime'] = strtotime($result[$i]['datetime']);
		$latlng_ = explode(',', $result[$i]['value']); // Decomposition de value

		$tabDetails[$i]['latitude'] = round(floatval(trim($latlng_[0])), 7);
		$tabDetails[$i]['longitude'] = round(floatval(trim($latlng_[1])), 7);

		$tabDetails[$i]['latlng'] = $tabDetails[$i]['latitude'].', '.$tabDetails[$i]['longitude'];

		$tabDetails[$i]['altitude'] = round(($latlng_[2]), 2);
		$tabDetails[$i]['pcBatterie'] = intval($latlng_[3]);
		$tabDetails[$i]['SSID'] = trim($latlng_[4]);
		$tabDetails[$i]['inhibe'] = 0;

		// Normalisation de l'activité
		$activite = strtoupper(@$latlng_[5][1]);
		$tabDetails[$i]['activite'] = $activite;

		if ($i > 0) {
			$tabDetails[$i]['ecart'] = abs(round(mg::getDistance($tabDetails[$i-1]['latitude'], $tabDetails[$i-1]['longitude'], $tabDetails[$i]['latitude'], $tabDetails[$i]['longitude'], 'k'), 4));
			$tabDetails[$i]['dureeEcart'] = ($tabDetails[$i]['datetime'] - $tabDetails[$i-1]['datetime']);
			$tabDetails[$i]['vitesseEcart'] = round($tabDetails[$i]['ecart'] / $tabDetails[$i]['dureeEcart']*3600, 1);

				// Force à 'I' SI SSID renseigné
				if ($tabDetails[$i]['SSID'] != 'Pas de SSID' ) {
					$tabDetails[$i]['activite'] = 'I';
				}

			// Inhibe points en erreur
			if (
				($tabDetails[$i]['activite'] == 'I' && $tabDetails[$i]['ecart'] == 0) ||
				($tabDetails[$i]['activite'] != 'I' && $tabDetails[$i]['ecart'] < 0.001) ||
				($tabDetails[$i]['activite'] == 'I' && $tabDetails[$i]['vitesseEcart'] > 1) ||
				($tabDetails[$i]['activite'] == 'E' && $tabDetails[$i]['vitesseEcart'] > 7 && $tabDetails[$i]['dureeEcart'] > 4) /*|| // pour buffer JC
				($tabDetails[$i]['activite'] == 'V' && $tabDetails[$i]['vitesseEcart'] > 140)*/)
				{
				$tabDetails[$i]['inhibe'] = 1;
			}
		}
	}
	return $tabDetails;
}

?>