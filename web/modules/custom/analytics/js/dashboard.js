
(function ($, drupalSettings) {
  // chart function
  chartSettings(drupalSettings.chart1.dataArray, 'chart1');
  chartSettings(drupalSettings.chart2.dataArray, 'chart2');
  chartSettings(drupalSettings.chart3.dataArray, 'chart3');
  chartSettings(drupalSettings.chart4.dataArray, 'chart4');
  $('.loader').hide();
  function chartSettings(dataArray, id) {
    // chart settings start.
    var optionsChart = {
      series: dataArray,
      chart: {
        id: id,
        type: 'bar',
        height: 350,
        stacked: true,
      },
      plotOptions: {
        bar: {
          horizontal: false,
          columnWidth: '60%',
          // endingShape: 'rounded',
          // borderRadius: 5,
          // borderRadiusApplication: 'end',
        },
      },
      dataLabels: {
        enabled: false
      },
      stroke: {
        show: true,
        width: 2,
        colors: ['transparent']
      },
      xaxis: {
        categories: drupalSettings.dates,
      },
      yaxis: {
        title: {
          text: ''
        },
        labels: {
          formatter: function(val) {
            return formatChange(val, id);
          }
        }
      },
      fill: {
        opacity: 1,
      }
    };

    var chart = new ApexCharts(document.querySelector("#" + id), optionsChart);
    chart.render();
  }

  $('.charts-selects select').change(function() {
    $.ajax({
      "url": '/ajax-dashboard',
      "dataType": 'json',
      "data": {'attribute': $('.charts-dropdown-attributes select').val(), 'carrier': $('.charts-dropdown-carrier select').val(), 'customer': $('.charts-dropdown-customer select').val(), 'month': $('.charts-dropdown-months select').val(), 'year': $('.charts-dropdown-years select').val(), 'partner': $('.charts-dropdown-partner select').val()},
      "beforeSend": function () {
        $('.loader').show();
      },
      "success": function (response) {
        ApexCharts.exec('chart1', 'updateOptions', {
          xaxis: {
            categories: response.dates
          },
          series: response.finalData.chart1
        }, false, true);
        ApexCharts.exec('chart2', 'updateOptions', {
          xaxis: {
            categories: response.dates
          },
          series: response.finalData.chart2
        }, false, true);
        ApexCharts.exec('chart3', 'updateOptions', {
          xaxis: {
            categories: response.dates
          },
          series: response.finalData.chart3
        }, false, true);
        ApexCharts.exec('chart4', 'updateOptions', {
          xaxis: {
            categories: response.dates
          },
          series: response.finalData.chart4
        }, false, true);
        // change banner numbers
        $('.banner-5 .banner-number').html(formatChange(Math.round(response.content.averageApi)));
        $('.banner-4 .banner-number').html(formatChange(Math.round(response.content.averageLatency)));
        $('.banner-3 .banner-number').html(formatChange(Math.round(response.content.highestApi)));
        $('.banner-2 .banner-number').html(formatChange(Math.round(response.content.totalUnsuccessCalls)));
        $('.banner-1 .banner-number').html(formatChange(Math.round(response.content.totalSuccessCalls)));

        // change average banner numbers
        $('.banner-1 .successfull-avg').html('Average ' + formatChange(Math.round(response.content.totalSuccessCallsAvg)) + ' / day');
        $('.banner-2 .unsuccessfull-avg').html('Average ' + formatChange(Math.round(response.content.totalUnsuccessCallsAvg)) + ' / day');
        setTimeout(() => {
          $('.loader').hide();
        }, 1000);
      }
    });
  });

  $('.chart-2-status-dropdown select').change(function() {
    $.ajax({
      "url": '/ajax-dashboard',
      "dataType": 'json',
      "data": {'api_status': $('.chart-2-status-dropdown select').val(), 'month': $('.charts-dropdown-months select').val(), 'year': $('.charts-dropdown-years select').val() },
      "beforeSend": function () {
        $('.loader').show();
      },
      "success": function (response) {
        ApexCharts.exec('chart2', 'updateSeries', response.finalData.chart2, true);
        setTimeout(() => {
          $('.loader').hide();
        }, 1000);
      }
    });
  });

  function formatChange(val, id = null) {
    var num = new Intl.NumberFormat('en-US', { style: 'decimal' }).format(val);
    return (id == 'chart4') ? '$' + num : num;
  }

})(jQuery, drupalSettings);