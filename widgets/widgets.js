/* ********************************************************************************************************************
********************************************** .JS SPECIFIQUE AUX WIDGETS *********************************************
******************************************************************************************************************** */

/* ------------------------------------------------ SLIDER BUTTON -------------------------------------------------- */
function sliderButton(f) {
	if (f.type == 'sliderbutton') {
		f.cmd.find('#input-group').attr('style','top:'+f.stateTop);
		f.cmd.find('#slider').remove();
		f.cmd.find('.state_MG').remove();
		  
		var pathImg = f.path+'/img/img_Numerique/sliderButton';
		f.height = f.width = 60*f.taille; 	
		
		f.cmd.find('#minus').attr({'height': f.height+'px', 'src': pathImg+'/minus_'+f.style+'_'+f.minusColor+'.png'});
		f.cmd.find('#plus').attr({'height': f.height+'px', 'src': pathImg+'/plus_'+f.style+'_'+f.plusColor+'.png'});
		
		f.cmd.find('input.input_MG').css({'font-size': f.stateSize+'px', 'font-weight': 'bold', 'height': f.height+'px', 'width': f.width+'px', 'background-image':'url('+pathImg+'/'+f.style+'.png)','background-size': 'auto '+f.height+'px'});
		
		f.cmd.find('#sliderButton').attr('style','color:'+f.stateColor+'!important;font-weight: bold!important');
		
	} else if (f.cmdSubtype == 'slider') { f.cmd.find('.sliderButton').remove(); }
}

/* ---------------------------------------------- AFFICHAGE DU TITRE ----------------------------------------------- */
function setTitre(f) {
	if (f.titreSize == 'true') {
		f.cmd.find('.titre_MG').text(f.titre);

		f.cmd.find('.titre_MG').on('click',function(){
			graph(f.name, f.name_displayId);
		});

		// Grisé du titre ET de l'image
		if (f.titreGrise == 'true' && f.state <= 0) {
			f.cmd.find('.titre_MG').addClass('imgGrise');
		} else {
			f.cmd.find('.titre_MG').removeClass('imgGrise');
		}

		// Blink du titre
		if (f.titreBlink == 'true' || f.state > 0 && f.cmdType == 'info' && f.cmdSubtype == 'binary') {
			f.cmd.find('.titre_MG').addClass('blinkColor');
		} else {
			f.cmd.find('.titre_MG').removeClass('blinkColor');
		}
	} else { f.cmd.find('.titre_MG').remove(); }
}
/* --------------------------------------------- CALCUL DU NOM DES IMG --------------------------------------------- */
function getImgName(f) {
	if (f.sousType != 'sansimg') {
//console.log('getImgName : '+f.sousType+' - '+f.cmdSubtype+' - '+f.imgName+' - '+f.srcImg);
		if (f.imgName.indexOf('Jauge') < 0) {
			f.cmd.find('.widget_MG').addClass(f.imgName+'Jauge');
		} else {
			f.cmd.find('.widget_MG').addClass(f.type+'JaugeName');
		}
		
//		f.srcImg = '';

		if (f.cmdSubtype == 'string') {
			f.srcImg = '';
			return;
		}

		etatMin_ = (f.etatMin ? f.etatMin : f.minValue);
		etatMax_ = (f.etatMax ? f.etatMax : f.maxValue);

		// Chemin de base
		if (f.cmdSubtype == 'binary' || f.cmdSubtype == 'other' ) {
			var imgPathPartiel =	f.path+'/img/img_Binaire';
		} else {
			var imgPathPartiel = f.path+'/img/img_Numerique';
			}
			var imgPath = imgPathPartiel + '/' + (f.type != '' ? f.type : '') + '/' + f.imgName ;

		// Nom des images NUMERIQUE
		if (f.cmdSubtype == 'numeric' || f.cmdSubtype == 'slider') {
			// Si image multiple
			if (f.nbImg > 1) {
				if (etatMin_ < 0) {
					var imgIncrement = (etatMax_ - etatMin_) / (f.nbImg-1);
					var numImg = Math.abs(Math.round(((f.state - etatMin_) / imgIncrement)+0.5)-1);
				}
				else {
					var plageEtat = etatMax_ - etatMin_ + 1;
					var imgIncrement = f.nbImg / plageEtat;
					var etatAffichage = f.state % plageEtat;
					var numImg = Math.round(imgIncrement * etatAffichage + 0.5) - 1;
				}
				// Mise en forme et calcul du chemin complet de l'image
				var numImgAff = 0;
				if (numImg < 10) { numImgAff = "00" + Math.abs(numImg); }
				else if (numImg < 100) { numImgAff = "0" + Math.abs(numImg); }
				f.srcImg = imgPath + '/' + f.imgName + '-' + numImgAff + '.png';

			// Nom des images simple
			} else {
			// Cas spécifiques des mono-image Numerique
			var imgPath = imgPathPartiel + '/' + (f.type != '' ? f.type : '');
				f.srcImg = imgPath + '/' + f.imgName + '.png';
			}

		// Nom des images BINAIRE
		} else {
			if (f.state > 0) {
				f.srcImg = imgPath + '_ON.png';
			}else {
				f.srcImg = imgPath + '_OFF.png';
			}
			/* ***** Pour IMG en cercle ) ***** */
			if	(f.formCircle == 'true') {
					f.srcImg = imgPath + '.png';
			}
		}
	}
}

/* -------------------------------------------------- ACTIONS BINAIRES ----------------------------------------------*/
function setActionBinaire(f) {
	if (f.cmdtype != 'action' && f.cmdSubtype != 'other') { return; }
		if (f.state == '1' || f.state == 1 || f.state == '99' || f.state == 99 || f.state == 'on') {
			if (jeedom.cmd.normalizeName(f.name) == 'on') {
				f.cmd.hide();
			}else{
				f.cmd.show();
				f.srcImg = f.path+'/img/img_Binaire/' + (f.type != '' ? f.type : '') + '/' + f.imgName + '_ON.png';
				f.cmd.find('.img_MG').attr({'src': f.srcImg});
			}
	} else {
		if (jeedom.cmd.normalizeName(f.name) == 'off') {
			f.cmd.hide();
		}else{
			f.cmd.show();
			f.srcImg = f.path+'/img/img_Binaire/' + (f.type != '' ? f.type : '') + '/' + f.imgName + '_OFF.png';
			f.cmd.find('.img_MG').attr({'src': f.srcImg});
		}
	}
}

/* ------------------------------------------------- Clic sur Widget ----------------------------------------------- */
function clicWidget(f) {
	if (f.cmdType != 'action' && f.cmdType != 'slider') {
		f.cmd.find('.widget_MG').on('click',function(){
			// Appel des graphiques
			if (f.appelScenario == '') {
				graph(f.name, f.name_displayId);
			// Appel d'un scénario
			} else {
				jeedom.scenario.changeState({
					id: f.appelScenario, state: 'start'
				});
			}
		});
	}
}

/* ------------------------------------------------- IMG DU WIDGET ------------------------------------------------- */
function setImg(f) {
	if (f.srcImg != '' && f.sousType != 'sansimg') {
			f.cmd.find('.img_MG').attr({'src': f.srcImg});

		// Hauteur minimale du widget calculée
		minHight = f.imgHeight*f.taille + 16 + (f.timerType != '' ? 30 : 0);
		f.cmd.css ({
			'min-height': minHight+'px',
			'min-width': (f.imgWidth*f.taille+30)+'px'
		});
		f.cmd.find('.widget_MG').css ({
			'width': f.imgWidth*f.taille+'px',
			'height': f.imgHeight*f.taille+'px'
		});
		f.cmd.find('.img_MG').css ({
			'width': f.imgWidth*f.taille+'px',
			'height': f.imgHeight*f.taille+'px',
			'transform': 'scale('+f.ratio+', 1)'
		});

		/* ***** Si image dans un cercle ***** */
		if	(f.formCircle == 'true') {
			if (f.state > 0) {
				f.cmd.find('.img_MG').css ({
					'border-radius': '50%',
					'border': '3px solid green'
				});
			} else {
				f.cmd.find('.img_MG').css ({
					'border-radius': '50%',
					'border': '3px solid red',
				});
			}
		}

		/* ***	Grisé de l'image *** */
		if (f.imgGrise == 'true' && f.state <= 0) {
			f.cmd.find('.img_MG').addClass('imgGrise');
		} else {
			f.cmd.find('.img_MG').removeClass('imgGrise');
		}
	} else {
		f.cmd.find('.img_MG').remove()
	}
}

/* ------------------------------------------------- IMAGE AIGUILLE ------------------------------------------------ */
function setAiguille(f) {
	if (f.type == 'jauges' && f.sousType == 'jauge') {
		var srcImgAiguille = f.srcImg.replace('.png', '-aiguille.png');
		srcImgAiguille = 'url("'+srcImgAiguille+'") no-repeat scroll center transparent';

		var angle = Math.round((360-f.angleMort) / (f.maxValue-(f.minValue)) * (f.state-(f.minValue))) + 180 + f.angleMort/2;
			f.cmd.find('.aiguille_MG').css ({	'transform': 'scale('+f.taille*f.aiguilleTaille+')' });
		f.cmd.find('.aiguille_MG').css({
			'background':srcImgAiguille,
			'width':f.imgWidth+'px', 'height':f.imgHeight+'px',
			'transform':'rotate('+angle+'deg) scale('+(f.taille*f.aiguilleTaille)+')',
			'top':-((1-f.taille)*f.imgHeight/2+(f.imgHeight*f.taille))+'px',
			'left':-((f.taille<=1) ?1-f.taille : 0)*(f.imgWidth/2)+'px',
		});
	} else {
		f.cmd.find('.aiguille_MG').remove();
	}
}

/* ----------------------------------------------------- STATE ----------------------------------------------------- */
function setState(f) {
	if (f.stateSize > 0 && f.cmdSubtype != 'slider') {
		if (f.imgName.indexOf('Jauge') < 0) {
			f.cmd.find('.state_MG').addClass(f.imgName+'State');
		} else {
			f.cmd.find('.state_MG').addClass(f.type+'JaugeState');
		}
		
		// Positionnement de state
		f.cmd.find('.state_MG').css ({ 'position':'absolute' });
		if (f.stateTop != 0) { f.cmd.find('.state_MG').css ({ 'top':f.stateTop }); }
		if (f.stateLeft != 0) { f.cmd.find('.state_MG').css ({ 'left':f.stateLeft }); }

		// pour les string
		if (f.cmdSubtype == 'string' ) {
			if (f.stateColor != '') { f.cmd.find('.state_MG').css ({ 'color':f.stateColor }); }
			f.cmd.find('.state_MG').css ({ 'font-size':f.stateSize*f.taille });
		
		// Pour les numériques et Binaire
		} else {
			f.state = parseFloat((f.state)).toFixed(f.stateRound);

			if (f.type == 'compteurs') {
			// pose des int et dec avec les style imgName + Int/Dec
				f.cmd.find('.state_MG').remove();
				var intNum = Math.trunc(f.state);
				f.cmd.find('.partInt').text(intNum);
				f.cmd.find('.partInt').addClass(f.imgName+'Int');

				var decNum = Math.round((f.state - intNum)*10);
				decNum = decNum < 10 ? decNum : 0;
				f.cmd.find('.partDec').text(decNum);
				f.cmd.find('.partDec').addClass(f.imgName+'Dec');
				f.cmd.find('.unite_MG').addClass(f.imgName+'Unite');
			} else {
			// Pas de state
				f.cmd.find('.partDec').remove();
				f.cmd.find('.partInt').remove();
				f.cmd.find('.unite_MG').remove();
			}

			if (f.type != 'compteurs') {
				// Pose de state standard 'state unité)
				if (f.stateSize > 0) {
					f.stateHTML_1 = '<span position:relative; style="color:'+f.stateColor+';font-size:'+f.stateSize*f.taille+'px;font-family : "+f.font+";">';
					f.stateHTML_2 = '</span>'+ '<span style="color:'+f.uniteColor+';font-size:'+f.stateSize*f.taille*0.7+'px;"> '+f.unite+'</span>';
					f.cmd.find('.state_MG').html(f.stateHTML_1+f.state+f.stateHTML_2);
				// Pas de state
				} else {
					f.cmd.find('.state_MG').remove();
				}
			}
		}
	// stateSize <= 0
	} else { 
		f.cmd.find('.state_MG').remove();
		f.cmd.find('.unite_MG').remove();
	}	
}

/* ------------------------------------------------- AFFICHAGE DU TIMER ---------------------------------------------*/
function timer(f) {
		// Positionnement du timer
		f.cmd.find('.timer_MG').css ({ 'position':'absolute'+'!important' });
		if (f.stimerTop != 0) { f.cmd.find('.timer_MG').css ({ 'top':f.timerTop }); }
		if (f.timerLeft != 0) { f.cmd.find('.timer_MG').css ({ 'left':f.timerLeft }); }

	if (f.timerType  != '') {
		if (f.imgName.indexOf('Jauge') < 0) {
			f.cmd.find('.timer_MG').addClass(f.imgName+'Timer');
		} else {
			f.cmd.find('.timer_MG').addClass(f.type+'JaugeTimer');
		}

		// Affichage du tooltips
		var tooltips = "<div class='tooltips_MG'><span>";
		if (f.maxHistoryValue > 0 && f.cmdSubtype == 'numeric') {
			tooltips += 'Min : ' + f.minHistoryValue + ' ' + f.unite + ' <br> Moyenne : ' + f.averageHistoryValue + ' ' + f.unite + ' <br> Max : ' + f.maxHistoryValue + ' ' + f.unite + '<br>';
		}
		if (f._options.valueDate) {
			tooltips += "<span> Date valeur : " + f._options.valueDate + " <br> Collectée : " + f._options.collectDate;
		}
		tooltips += "</span></div>";
		f.cmd.find('.timer_MG').attr('title', tooltips);

		// Affichage du timer
		if (f.timerType == 'duree') {
			jeedom.cmd.displayDuration(f._options.valueDate, f.cmd.find('.timer_MG'));
		 }
		else if (f.timerType == 'dateVal') {
			var date = new Date(Math.abs(f.state)*1000);
			var format = $.datepicker.formatDate('D d M', date);
			var h = date.getHours(); if (h<10) {h = "0"+h}
			var m = date.getMinutes(); if (m<10) {m = "0"+m}
			var time = "à "+h+" h "+m+" m";
			f.cmd.find('.timer_MG').html(format+'<br>'+time);
		}
		else if (f.timerType == 'date') {
			var date = new Date(f._options.valueDate.replace(' ', 'T'));
			var t = f._options.valueDate.split(/[- :]/);
			var format = $.datepicker.formatDate('D d M', date);
			var time = "à "+t[3]+":"+t[4];
			f.cmd.find('.timer_MG').html(format+'<br>'+time);
		}
		else if (f.timerType == 'heure') {
			var date = new Date(f._options.valueDate.replace(' ', 'T'));
			var t = f._options.valueDate.split(/[- :]/);
			var time = "à "+t[3]+":"+t[4]+":"+t[5];
			f.cmd.find('.timer_MG').html(time);
		}
	} else { f.cmd.find('.timer_MG').remove(); }
}

/* -------------------------------------------- APPEL MODALE DES GRAPHIQUES -----------------------------------------*/
function graph(name, name_displayId) {
	//remove any previously loaded history:
	if (jeedom.history.chart['div_historyChart'] != undefined) {
	  while (jeedom.history.chart['div_historyChart'].chart.series.length > 0) {
		jeedom.history.chart['div_historyChart'].chart.series[0].remove(true)
	  }
	  delete jeedom.history.chart['div_historyChart']
	}

	modal = $('#md_modal3')
	if (modal.is(':visible')) {
		modal.modal('dispose');
	}
	modal.dialog({title: "Histo "+name+" ("+name_displayId+")"});
	modal.load('index.php?v=d&modal=cmd.history&id='+name_displayId).dialog('open')
}

/* ---------------------------------------------------- SLIDER ----------------------------------------------------- */
function setSlider(f) {
	if (f.cmdSubtype == 'slider' && f.type != 'sliderbutton') {
		f.cmd.find('.slider_MG').css ({ 'width':(f.imgWidth*f.taille+30)+'px' });

		f.cmd.find('.slider-tooltip').slider({
			range: "min",
			min: parseInt(f.minValue),
			max: parseInt(f.maxValue),
			value: parseInt(f._options.display_value),
			slide: function(event, ui) {
				f.cmd.find('.tooltiptext').text(ui.value);	
			}
		});
	} else if (f.cmdSubtype == 'slider') { 
		f.cmd.find('.slider').remove(); 
	}
}

/* -------------------------------------------------- GRADUATIONS -------------------------------------------------- */
function setGraduations(f, classGradMin, classGrad1, classGrad2, classGradMid, classGrad4, classGrad5, classGradMax) {
	if (f.type == 'jauges' && f.sousType == 'jauge' && f.sizeGrad > 0) {
		var inc = (f.maxValue - f.minValue)/6;
		f.cmd.find('.gradMin').text(Math.round(f.minValue + 0*inc));
		f.cmd.find('.grad1').text(Math.round(f.minValue + 1*inc));
		f.cmd.find('.grad2').text(Math.round(f.minValue + 2*inc));
		f.cmd.find('.gradMid').text(Math.round(f.minValue + 3*inc));
		f.cmd.find('.grad4').text(Math.round(f.minValue + 4*inc));
		f.cmd.find('.grad5').text(Math.round(f.minValue + 5*inc));
		f.cmd.find('.gradMax').text(Math.round(f.minValue + 6*inc));

		// Coordonnées du centre des Graduations
		f.cmd.find('.graduations_MG').css({
			'top':'47%',
			'left':'44.5%',
			'font-size': f.sizeGrad*f.taille+'px'
		});

		// Diamètre jauge - 10%
		var width = (0.5*f.imgWidth*f.taille)*0.75;

		// Calcul de la position des graduations
		var rad = 0.0174533;
		var incAngle = (360-f.angleMort)/6;
		var debAngleV = f.angleMort/2 + 90;
		var debAngleH = f.angleMort/2 + 180;

		f.cmd.find('.gradMin').css({ 'top':width * Math.sin((0*incAngle+debAngleV)*rad)+'px', 'left':width * Math.sin((0*incAngle+debAngleH)*rad)+'px' });
		f.cmd.find('.grad1').css({ 'top':width * Math.sin((1*incAngle+debAngleV)*rad)+'px', 'left':width * Math.sin((1*incAngle+debAngleH)*rad)+'px' });
		f.cmd.find('.grad2').css({ 'top':width * Math.sin((2*incAngle+debAngleV)*rad)+'px', 'left':width * Math.sin((2*incAngle+debAngleH)*rad)+'px' });
		f.cmd.find('.gradMid').css({ 'top':width * Math.sin((3*incAngle+debAngleV)*rad)+'px', 'left':width * Math.sin((3*incAngle+debAngleH)*rad)+'px' });
		f.cmd.find('.grad4').css({ 'top':width * Math.sin((4*incAngle+debAngleV)*rad)+'px', 'left':width * Math.sin((4*incAngle+debAngleH)*rad)+'px' });
		f.cmd.find('.grad5').css({ 'top':width * Math.sin((5*incAngle+debAngleV)*rad)+'px', 'left':width * Math.sin((5*incAngle+debAngleH)*rad)+'px' });
		f.cmd.find('.gradMax').css({ 'top':width * Math.sin((6*incAngle+debAngleV)*rad)+'px', 'left':width * Math.sin((6*incAngle+debAngleH)*rad)+'px' });
	} else {
		f.cmd.find('.graduations_MG').remove();
	}
}

/* --------------------------------- ROUTINE CALCUL VALEUR PAR DEFAUT DES PARAMETRES --------------------------------*/
function valDefaut(variable, defaut) {
	if (typeof(variable) != "undefined" && variable !== null && variable !== '') {
		if (variable.charAt(0) != '#') {
//			console.log(variable, defaut, '=>', variable)
			return variable;
		}
	}
//	console.log(variable, defaut, '=>', defaut)
	return defaut;
}

/* -------------- Lecture et Enregistrement d'une variable en BdD (selon la présence ou non de '$Value' -------------*/
function API_Var(name, value='') {
		IP = '192.168.2.196'; 				// IP de jeedom
		apikey = 'w35cb9cmgg2ehbsbca1h'; 		// apikey de jeedom
		var params = { 'apikey' : apikey, 'type' : 'variable', 'name' : name, 'value' : value };
		var requete = "http://"+IP+"/core/api/jeeApi.php?" + jQuery.param(params);

		/* Affiche l'alerte mais ne renvoie pas la valeur (asynchrone)
			ret = $.get( requete, function( data ) {
		//		alert( "Data Loaded: " + data );
			});
		*/
		/*
		data = '';
		success = '';
		dataType = '';
		ret = $.get({
		  url: requete,// mandatory
		  data: data,
		  success: success,
		  dataType: dataType,
		  async:true // to make it synchronous
		});
		console.log(ret+' - '+data+' - '+success+' ****************');
		*/
}

/* -------------------------------------------- ROUTINE HIGHCHART VU METRE ------------------------------------------*/
function highChartVuMetre(f) {
	f.cmd.find('.highChart').empty().highcharts({
		chart: {
			type: 'gauge',
			style: {
				fontFamily: f.font,
				},
			height: f.imgHeight,
			width: f.imgWidth,
			plotBorderWidth: f.fondEpaisCadre,
			plotBorderColor: f.fondColorCadre,
			// Couleur de fin (poli-Dégradé
			plotBackgroundColor: {
			linearGradient: { x1: 0,  x2: 0,  y1: 0,  y2:1 },
				stops: [
					[0, f.colorBackground],
					[0.3, f.fondColor2],
					[1, f.fondColor3]
				]
			},
			spacingTop: f.spacing, spacingLeft: f.spacing, spacingRight: f.spacing, spacingBottom: f.spacing,
			},
			title: { text: '' },
			credits: { enabled: false },
			// ----------------------------------------------------- VUMETRE ----------------------------------------------
			pane: [{
			startAngle: f.startAngle,
			endAngle: f.endAngle,
			background: null,
				center: ['50%', f.imgHeight*1.05+'px'], // Hauteur de la zone de jauge
				size: f.imgWidth,
			}],
		plotOptions: {
			gauge: {
			// ------------------------------------ AFFICHAGE VALEUR VU METRE -------------------------------------
				dataLabels: {
					enabled: f.titreSize == 'true' ? true : false,
					borderWidth: 0,
					align: 'center',
					formatter: function () { 
					return '<span position:relative; style="color:'+f.stateColor+';font-size:'+f.stateSize*f.taille+'px;font-family : "+f.font+";">'+ this.point.y.toFixed(f.stateRound) + '<span style="color:'+f.uniteColor+';font-size:'+f.stateSize*f.taille*0.7+'px;"> '+f.unite+'</span>'},
					y: parseFloat(f.stateTop),
				},
				// -------------------------------------------- AFFICHAGE AIGUILLE ------------------------------------
				dial: {
					radius: f.aiguilleTaille,
					baseLength: '100%',
					rearLength: '-60%',
					backgroundColor: f.aiguilleColor,
					baseWidth: Math.round(1.5*f.taille+0.5),
				}
			}
		},
		// ------------------------------------------------------- AXE Y ----------------------------------------------
		yAxis: [{
			pointStart: f.minValue,
			min: f.minValue,
			max: f.maxValue,
			tickLength: 10*f.taille,
			minorTickPosition: 'inside',
			tickPosition: 'outside',
			type: f.jaugeType,
			tickInterval: f.jaugeIntervalle,
			minorTickInterval: 'auto',
			labels: {
				rotation: 'auto',
				distance: 10*f.taille,
				style: { color: "silver", fontSize: f.sizeGrad*f.taille, }
			},
			// Def bande rouge
			plotBands: [
				{from: f.alerteMin, to: f.alerteMax, color: f.alerteColor, innerRadius: '95%', outerRadius: '100%'},
//				{ from: V0#id#, to: V1#id#, color: C1#id# }, // green couleur 1
			],
		}],
		series: [{
			data: [f.state],
			yAxis: 0,
			enableMouseTracking: false,
		}]
	});
	f.cmd.find('.highChart').append();
}

/* -------------------------------------------- ROUTINE HIGHCHART JAUGE -------------------------------------------- */
	function highChartJauge (f) {
	f.cmd.find('.highChart').empty().highcharts({
		chart: {
			type: 'gauge',
			style: {
				fontFamily: f.font,
				},
				height: f.imgHeight,
				width: f.imgWidth,
				plotBackgroundImage: f.srcImg,
				plotBorderWidth: f.fondEpaisCadre,
				plotBorderColor: f.fondColorCadre,
				plotShadow: false,
				plotBackgroundColor: f.colorBackground,
				spacingTop: f.spacing, spacingLeft: f.spacing, spacingRight: f.spacing, spacingBottom: f.spacing,
		},
		title: { text: '' },
		credits: { enabled: false },
		// -------------------------------------------------- JAUGE -------------------------------------------
		pane: [{
			startAngle: f.startAngle,
			endAngle: f.endAngle,
			background: [{
				backgroundColor: f.colorBackground,
				borderWidth: 0,
				outerRadius: '100%',
				innerRadius: '0%'
			}],
		}],
		// Tracé de la jauge
		yAxis: [{
			pointStart: f.minValue,
			min: f.minValue,
			max: f.maxValue,
			tickLength: 10*f.taille,
			minorTickPosition: 'inside',
			tickPosition: 'outside',
			type: f.jaugeType,
			tickInterval: f.jaugeIntervalle,
			minorTickInterval: 'auto',
			 labels: {
				rotation: 'auto',
				distance: 15*f.taille
			},
		}],
		plotOptions: {
			gauge: {
					dataLabels: {
					enabled: f.titreSize == 'true' ? true : false,
						borderWidth: 0,
						align: 'center',
						formatter: function () {
						return '<span position:relative; style="color:'+f.stateColor+';font-size:'+f.stateSize*f.taille+'px;font-family : "+f.font+";">'+ this.point.y.toFixed(f.stateRound) + '<span style="color:'+f.uniteColor+';font-size:'+f.stateSize*f.taille*0.7+'px;"> '+f.unite+'</span>'},
						y: parseFloat(f.stateTop),
					},
				// ------------------------------------------ AFFICHAGE AIGUILLE ----------------------------------
				dial: {
					radius: f.aiguilleTaille,
					rearLength:0,
					backgroundColor: f.aiguilleColor,
					baseWidth:4*f.taille,
					topWidth:1,
					borderWidth: 1,
					borderColor: "#F00",
				},
				// Centre de l'aiguille
				pivot: {
					radius: "8",
					borderWidth: 1,
					borderColor: 'white',
					backgroundColor: 'black',
				},
			}
		},
		// ----------------------------------------------------- AXE Y --------------------------------------------
		yAxis: {
			min: f.minValue,
			max: f.maxValue,
			minorTickInterval: 'auto',
			minorTickWidth: 1,
			minorTickLength: 10,
			minorTickPosition: 'inside',
			tickPixelInterval: 50,
			tickWidth: 2,
			tickPosition: 'inside',
			tickLength: 25,
			labels: {
				step: 1,  // espacement des valeurs : 1
				rotation: 'auto', //orientation des valeurs des textes (défaut 'auto' 0 horizontal a 360  ...)
				padding: 1,
				style: {
					color: "silver",
					fontSize: f.sizeGrad*f.taille,
				}
			},
		  // Def bande rouge
			plotBands: [{from: f.alerteMin, to: f.alerteMax, color: f.alerteColor, innerRadius: '92%', outerRadius: '100%'}]
		},
		series: [{
			data: [f.state],
			yAxis: 0,
			enableMouseTracking: false,
		}]
	});
	f.cmd.find('.highChart').append();
}

