setTimeout(function() {
   var data = {
      action: 'capture_country'
   };

   jQuery.post(simpleChartParams.ajax_url, data, function(response) {
      //console.log(response); //was for debug
   });
}, 5000);  