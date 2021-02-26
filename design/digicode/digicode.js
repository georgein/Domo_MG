jQuery.fn.extend({
  
  digicode: function(callback, delayReset) {
    
    this.html('<ul class="digiEvent"><li></li><li></li>	<li></li><li></li><li></li><li></li></ul><ul class="digicodePanel"><div><li>1</li><li>2</li><li>3</li></div><div><li>4</li><li>5</li><li>6</li></div><div><li>7</li><li>8</li><li>9</li></div><div><li>#</li><li>0</li><li class="digiReset">X</li></div></ul>');
    this.addClass('digicode');   
    this.delayReset = $.isNumeric(delayReset) ? delayReset : 4; //DELAY EN SECONDE POUR AUTO RESET CODE
    this.jeedomExecute = $.isFunction(callback) ? callback : (function () {}) ; 	
    this.keys = this.find('.digicodePanel li');
    this.Displays = this.find('.digiEvent li');
    this.inputs = [];
    this.timer = null;
    this.delayReset *= 1000;

    this.displayInputs = (function() {
      this.Displays.removeClass('digiFilled digiFilledOK');
      $.each(this.inputs, (function(i, e) {
        this.Displays.eq(i).addClass('digiFilled');
      }).bind(this));
    }).bind(this);
    
    this.clearCode = (function() {
      this.inputs = [];
      this.displayInputs();
      clearInterval(this.timer);
    }).bind(this);

    this.resetTimer = (function(resetTimer) {
      if (this.timer != null) {
        clearInterval(this.timer);
      }
      this.timer = setInterval(this.clearCode, this.delayReset);
    }).bind(this);
   
    this.codeReady = (function() {
      this.jeedomExecute(this.inputs.join(''));
        setTimeout((function() {
          this.Displays.addClass('digiFilledOK');
        }).bind(this), 200);
        setTimeout((function() {
          this.clearCode();
        }).bind(this), 500);
    }).bind(this);

    this.keys.on(('ontouchstart' in document.documentElement) ? "touchstart" : "click", (function(e) {
      var el = $(e.currentTarget);
      if (el.hasClass('digiReset')) {
        this.clearCode();
      }
      else {
        el.addClass('digiSel');
        this.inputs.push(el.text());
        this.displayInputs();
        this.resetTimer();
        if (this.inputs.length == 6) {
          this.codeReady();
        }
      }
    }).bind(this));

    this.keys.on('mouseup mouseleave touchend', function() {
      var el = $(this);          
      if (!el.hasClass('digiReset')) {
        setTimeout(function() { 
          el.removeClass('digiSel');
        }, 150);
      }
    });
        
  }
});