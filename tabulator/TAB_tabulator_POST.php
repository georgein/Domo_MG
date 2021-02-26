<head>
		<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
<!-- RETOUR A LA PAGE PRECEDENTE, mettre en REM pour pouvoir lire les messages -->
 <meta http-equiv="refresh" content="0;<?php echo $_SERVER['HTTP_REFERER']; ?>" /> <!-- <!-- mettre 10-60 pour lire les messages, 0 sinon -->
</head>

<style>
.page
{
	font-family: Verdana; /* police */
	color:black;
	font-weight: bold; /* fonte grasse du texte */
	text-align:center; /* alignement horizontal */
	font-size: 2em; /* taille de police relative */
}
</style>

<body style="background-color:#FFF4C4;"> <!-- #FFD264;#FFF4C4 -->
	<div class='page'>
		<img src='wait.gif'>
		<br><br>
	</div>
</body>

// <?php
// *************************************************** CODE PHP APPELE *************************************************
	$lngSegments = 2000; // Longueur maximum transmissible via l'API Jeedom
	
	$IP = trim(htmlspecialchars($_POST['IP'])); 				// IP de jeedom
	$apikey = trim(htmlspecialchars($_POST['apikey'])); 		// apikey de jeedom
	$numScenario = intval($_POST['numScenario']); 				// Scénario à appeler
	$submit = trim(htmlspecialchars($_POST['submit']));			// Ordre à éxécuter
	$nomTab = trim(htmlspecialchars($_POST['nomTab']));			// Nom de la table
	$nomTabOrg = trim(htmlspecialchars($_POST['nomTabOrg']));	// Nom de la table d'origine
	$message = trim(@$_POST['message']).trim(@$_POST['message']);// Contenu de la table 
	
	$lngMessage = strlen($message);
	$tabMessage = str_split($message, $lngSegments);
	$nbSegments = count($tabMessage);
	echo ("( Nombre de variable du _POST " . count($_POST) . " - Action : '$submit' - nomTab : '$nomTab' - nomTabOrg : '$nomTabOrg' - nbSegments : '$nbSegments' - lngMessage : '$lngMessage' )<br><br>");


	echo($message);
	// ENREGISTRER
	if ($submit == 'Enregistre') {
		if ($nomTabOrg != '') { $nomTab = $nomTabOrg; } // Sécurité pour éviter d'enregistrer sous un autre nom
		if ($nbSegments > 0) {
			for ($i=0; $i<$nbSegments; $i++) {
				API_Var($IP, $apikey, "_retFile$i", trim($tabMessage[$i], '"'));
				usleep(0.5*1000000);
			}
		} else {
			echo strtoupper("<br>*************** MESSAGE VIDE ACTION '$submit' ANNULEE !!! ***************<br>");
			return;
		}
	} 
	echo strtoupper("<br>*************** ACTION '$submit' EN COURS ***************<br>");
	
	// LANCEMENT SCENARIO DE GENERATION DU TABLEAU
	API_Scenario($IP, $apikey, $numScenario, 'start', "submit='$submit' nomTab='$nomTab' nbSegments='$nbSegments' lngMessage='$lngMessage'");

	// ATTENTE RETOUR
	usleep(1*1000000);
	$ret = API_Var($IP, $apikey, '_htmlOK', '0');
	for ($i=0;$i<15 && $ret!=='1';$i++) {
		usleep(0.1*1000000);
		$ret = API_Var($IP, $apikey, '_htmlOK');
	}
	echo $_SERVER['HTTP_REFERER'];

// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// Lecture et Enregistrement d'une variable en BdD (selon la présence ou non de '$Value'
function API_Var($IP, $apikey, $Name, $Value='') {
		$params = array(	'apikey'=>$apikey,
							'type'=>'variable',
							'name'=>$Name,
							'value'=>$Value);
		$urlJeedom = "http://$IP/core/api/jeeApi.php?" . http_build_query($params);
		return file_get_contents($urlJeedom);
//		echo $url;
	}
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// --------------------------------------------------------------------------------------------------------------------
// Lancement d'un scénario
function API_Scenario($IP, $apikey, $ID, $Action='start', $tag='') {
		echo strtoupper("<br>***************< $Action du scénario N° '$ID' ***************<br>");
		$params = array(	'apikey'=>$apikey,
							'type'=>'scenario',
							'id'=>$ID,
							'action'=>$Action,
							'tags'=>$tag
							);
		$urlJeedom = "http://$IP/core/api/jeeApi.php?" . http_build_query($params);
		$Value = trim(file_get_contents($urlJeedom));
	}
?>
