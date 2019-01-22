<?php
require_once('common/header_mturk.php');
require_once('common/navigation.php');
if($error) {
   return;
}
if(getYearForCurrentSeason($output) !== 1) {
   die('unable to get year for current season');
} else {
   $current_season = $output['season']['year'];
}
function getColor($regionID, $seasonID) {
   $colors =  ["#FF90C9","#B903AA","#D16100","#CC0744","#000035","#7B4F4B","#A1C299", // 1997-2003 (hidden by default)
 "#A079BF","#1CE6FF","#FF34FF","#FF4A46","#008941","#006FA6","#A30059","#7A4900","#0000A6","#63FFAC","#004D43","#FFAA00","#4FC601"]; // 2004~current, 2009 is hidden on the graph
   $idx = $seasonID - 1997;
   $color = $colors[$idx];
   return $color;
}

$regionID = intval($_GET["id"]);
$mturkID = $_SESSION['mturkId'];
$userID = getUserIDByMturkID($mturkID);
// echo ($userID);

loadUserPreferences_mturk($output, $userID);

if(isset($_REQUEST['skip_instructions'])) {
   $output['user_preferences']['skip_instructions'] = 1;
   if(saveUserPreferences_mturk($output, $userID, $output['user_preferences']) !== 1) {
      fail('Error updating preferences');
   }
}

//Epiweek info
if(getEpiweekInfo_mturk($output) !== 1) {
   fail('Error loading epiweek info');
}
//List of all regions
if(getRegionsExtended_mturk($output, $userID) !== 1) {
   fail('Error loading region details, history, or forecast');
}
?>

<?php
//Specific region
if(!isset($output['regions'][$regionID])) {
   fail('Invalid region_id');
}

//Forecast from last round
if(loadForecast_mturk($output, $userID, $regionID, true) !== 1) {
   fail('Error loading last week forecast');
}

$lastForecast = $output['forecast'];

$region = $output['regions'][$regionID];
$num = count($output['regions']);
//History for this region
$output['history'] = &$output['regions'][$regionID]['history'];
//User's previous forecast for this region
$output['forecast'] = &$output['regions'][$regionID]['forecast'];
// echo "-------------";
// echo (count($output['forecast']['wili']));

//Settings
$showPandemic = getPreference($output, 'advanced_pandemic', 'int');
//Calculate a few helpful stats
$firstWeekOfChart = 30;
$currentWeek = $output['epiweek']['round_epiweek'];
if(($currentWeek % 100) >= $firstWeekOfChart) {
   $yearStart = intval($currentWeek / 100);
} else {
   $yearStart = intval($currentWeek / 100) - 1;
}
$seasonStart = $yearStart * 100 + $firstWeekOfChart;
$seasonEnd = ($yearStart + 1) * 100 + ($firstWeekOfChart - 1);
$numPastWeeks = getDeltaWeeks($seasonStart, $currentWeek);
$numFutureWeeks = getDeltaWeeks($currentWeek, $seasonEnd);
$maxRegionalWILI = 0;
for($i = 0; $i < count($region['history']['wili']); $i++) {
   $epiweek = $region['history']['date'][$i];
   if($showPandemic || $epiweek < 200940 || $epiweek > 201020) {
      $maxRegionalWILI = max($maxRegionalWILI, $region['history']['wili'][$i]);
   }
}
max($region['history']['wili']);
$target = $seasonStart;
$seasonOffsets = array();
$seasonYears = array();
for($i = count($region['history']['date']) - 1; $i >= 0; $i--) {
   if($region['history']['date'][$i] <= $target) {
      array_push($seasonOffsets, $i);
      array_push($seasonYears, intval($target / 100));
      $target -= 100;
   }
}
if($seasonOffsets[count($seasonOffsets) - 1] != 0) {
   array_push($seasonOffsets, 0);
   array_push($seasonYears, intval($target / 100));
}
$seasonOffsets = array_reverse($seasonOffsets);
$seasonYears = array_reverse($seasonYears);
// echo ($seasonYears[count($seasonYears)-1]);
$seasonYears = array_slice($seasonYears, 0, count($seasonYears)-2);
// echo ($seasonYears[count($seasonYears)-1]);

if($regionID === 1 && getPreference($output, 'skip_instructions', 'int') !== 1) {
   ?>
   <div class="box_section">
      <div class="box_section_title">
         How to Enter Your Forecast
         <div class="box_section_subtitle">Draw your forecast curve across the chart by clicking and dragging.</div>
      </div>
      <div class="centered">
         <p>
            <b></b><br />
            &nbsp;<i class="fa fa-angle-right"></i>&nbsp; You can draw <i>in one motion</i> the entire trajectory.<br />
            &nbsp;<i class="fa fa-angle-right"></i>&nbsp; You can edit any part of your forecast by redrawing just that part.<br />
            &nbsp;<i class="fa fa-angle-right"></i>&nbsp; You can adjust a single point by dragging it up or down.<br />
            The animation below demonstrates these actions.
            (If you don't see the animation, click <a target="_blank" href="images/tutorial.gif">here</a>.)
         </p>
         <video width="1112" height="480" controls autoplay loop>
            <source src="images/tutorial.mp4" type="video/mp4">
            Your browser does not support the video tag.
         </video>
         <p>
            <?php
           createForm('reload', 'forecast_mturk.php?id=1', array('region_id', $regionID, 'skip_instructions', '1'));
           button('fa-check', 'I Understand', "submit('reload')");
            ?>
         </p>
      </div>
   </div>
   <?php
} else {
?>
<?php
foreach($output['regions'] as $r) {
   createForm('forecast_' . $r['id'], 'forecast_mturk.php#top', array('region_id', $r['id']));
}
?>
<?php fail('Whoa, your screen is too small! Please visit this site on a non-mobile device, or try to expand your browser window. Sorry about that!', 'box_nocanvas', true); ?>

<div id="box_main_ui">
   <div id="box_status" class="box_status any_neutral right">
      <div class="box_status_line">
         <div class="box_region_label">
            <?= htmlspecialchars($region['name']) ?>
            <span style="font-size: 0.67em;">
               [<?= htmlspecialchars($region['states']) ?>]
            </span>
         </div>
         <div style="float: right;">
            <?php button('fa-upload', 'Save &amp; Continue', "submitForecast(true)", '', 'button_submit'); ?>
         </div>
         <div class="box_status_info">
            <span id="status_message">Draw your forecast by clicking and dragging across the chart below.</span>
            <span id="status_icon"><i class="fa fa-info-circle"></i></span>
         </div>
         <div style="clear: both;"></div>
      </div>
   </div>
   <div id="box_side_bar">
      <div id="box_histories">
         <div class="box_decision_title centered" style="width: 100%;">History</div>
         <?php
         foreach($output['regions'] as $r) {
            if($r['id'] !== $regionID) continue;
            // print $regionID;
            ?>

            <div class="any_bold any_cursor_pointer" onclick="toggleSeasonList(<?= $r['id'] ?>)"><i id="checkbox_region_<?= $r['id'] ?>" class="fa fa-plus-square-o"></i>&nbsp;<?= htmlspecialchars($r['name']) ?></div>
            <div id="container_<?= $r['id'] ?>_all" class="any_hidden any_cursor_pointer" onclick="toggleAllSeasons(<?= $r['id'] ?>)">&nbsp;&nbsp;&nbsp;&nbsp;<i id="checkbox_<?= $r['id'] ?>_all" class="fa fa-square-o"></i>&nbsp;<span class="effect_tiny effect_italics">Show all</span></div>
            <?php
            $currentYear = $seasonYears[count($seasonYears) - 1];
            $numHHS = 11;
            if($regionID <= $numHHS) {
               foreach($seasonYears as $year) {
                  if($year == 2009 && $showPandemic !== 1) {
                     continue;
                  }
                  if($r['id'] == $regionID && $year == $currentYear) {
                     ?>
                     <div id="container_<?= $r['id'] ?>_<?= $year ?>" class="any_hidden any_cursor_pointer">&nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-check-square"></i>
                        <span class="effect_tiny"><?= sprintf('%d-now', $year) ?></span>
                     </div>
                     <?php
                  } else {
                     ?>
                     <div id="container_<?= $r['id'] ?>_<?= $year ?>" class="any_hidden any_cursor_pointer" onclick="toggleSeason(<?= $r['id'] ?>, <?= $year ?>)">&nbsp;&nbsp;&nbsp;&nbsp;<i id="checkbox_<?= $r['id'] ?>_<?= $year ?>" class="fa fa-square-o" style="color: <?= getColor($r['id'], $year) ?>"></i>
                        <span class="effect_tiny"><?= sprintf('%d-%s', $year, ($year == $currentYear) ? 'now' : '' . ($year + 1)) ?><?= ($year == 2009 ? ' pdm' : '')?></span>
                     </div>
                     <?php
                  }
               }
            } else { // for each states, data are only available starting 2010-2011 season
               foreach($seasonYears as $year) {
                  if($year <= 2009) {
                     continue;
                  }

                  if($year == 2009 && $showPandemic !== 1) {
                     continue;
                  }
                  if($r['id'] == $regionID && $year == $currentYear) {
                     ?>
                     <div id="container_<?= $r['id'] ?>_<?= $year ?>" class="any_hidden any_cursor_pointer">&nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-check-square"></i>
                        <span class="effect_tiny"><?= sprintf('%d-now', $year) ?></span>
                     </div>
                     <?php
                  } else {
                     ?>
                     <div id="container_<?= $r['id'] ?>_<?= $year ?>" class="any_hidden any_cursor_pointer" onclick="toggleSeason(<?= $r['id'] ?>, <?= $year ?>)">&nbsp;&nbsp;&nbsp;&nbsp;<i id="checkbox_<?= $r['id'] ?>_<?= $year ?>" class="fa fa-square-o" style="color: <?= getColor($r['id'], $year) ?>"></i>
                        <span class="effect_tiny"><?= sprintf('%d-%s', $year, ($year == $currentYear) ? 'now' : '' . ($year + 1)) ?><?= ($year == 2009 ? ' pdm' : '')?></span>
                     </div>
                     <?php
                  }
               }
            }

         }
         ?>
      </div></div><div id="box_canvas"><canvas id="canvas" width="800" height="400"></canvas></div>
</div>





<script src="js/forecast.js"></script>
<script>
   //globals
   //var DEBUG = <?= $output['user_id'] == 9 ? 'true' : 'false' ?>;
   //Axis range
   var currentWeek = <?= $currentWeek ?>;
   var numPastWeeks = <?= $numPastWeeks ?>;
   var numFutureWeeks = <?= $numFutureWeeks ?>;
   var totalWeeks = (numPastWeeks + 1 + numFutureWeeks);
   var xRange = [addEpiweeks(currentWeek, -numPastWeeks), addEpiweeks(currentWeek, +numFutureWeeks)];
   var yRange = [0, <?= ($maxRegionalWILI * 1.1) ?>];
   var regionID = <?= $regionID ?>;
   var seasonOffsets = [<?php foreach($seasonOffsets as $o){printf('%d,',$o);} ?>];
   var seasonYears = [<?php foreach($seasonYears as $y){printf('%d,',$y);} ?>];
   var seasonIndices = {<?php for($i = 0; $i < count($seasonYears); $i++){printf('\'%d\':%d,',$seasonYears[$i],$i);} ?>};
   var regionNames = [];
   var pastWili = [];
   var forecast = [];
   var curveStyles = {};
   <?php
   foreach($output['regions'] as $r) {
      ?>
      regionNames[<?= $r['id'] ?>] = '<?= $r['name'] ?>';
      pastWili[<?= $r['id'] ?>] = [<?php
         foreach($r['history']['wili'] as $v){printf('%.2f,',$v);}
      ?>];
      forecast[<?= $r['id'] ?>] = [<?php
         $offset = count($r['forecast']['date']);
         foreach($r['forecast']['date'] as $d) {
            if($d > $currentWeek) {
               $offset -= 1;
            }
         }
         $start = 0;
         $middle = min(count($r['forecast']['wili']) - $offset, $numFutureWeeks);
         $end = $numFutureWeeks;
         for($i = $start; $i < $middle; $i++){printf('%.3f,',$r['forecast']['wili'][$offset + $i]);}
         for($i = $middle; $i < $end; $i++){printf('0,');}
      ?>];
      curveStyles[<?= $r['id'] ?>] = {};
      <?php
      foreach($seasonYears as $year) {
         ?>
         curveStyles[<?= $r['id'] ?>][<?= $year ?>] = {color: '<?= getColor($r['id'], $year) ?>', size: 1, dash: []};
         <?php
      }
   }
   ?>
   var selectedSeasons = [];
   var showLastForecast = true;
   var lastForecast = [<?php foreach($lastForecast['wili'] as $v){printf('%.3f,', $v);} ?>];
   var timeoutID;
   var lastDrag = null;
   var tooltip = null;
   // nowcast
   var showNowcast = <?= (getPreference($output, '_delphi', 'int') == 1 && isset($output['nowcast'])) ? 'true' : 'false' ?>;
   <?php
   if(isset($output['nowcast'])) {
      printf('var nowcast = [%.5f, %.5f];', $output['nowcast']['value'], $output['nowcast']['std']);
   }
   ?>
   //x-axis
   function getChartWidth() {
      return canvas.width - marginLeft() - marginRight();
   }
   function getX(epiweek) {
      var ew = epiweekToDecimal(epiweek);
      var left = epiweekToDecimal(xRange[0]);
      var right = epiweekToDecimal(xRange[1]);
      return marginLeft() + (getChartWidth() / (right - left)) * (ew - left);
   }
   function getEpiweek(x) {
      var left = epiweekToDecimal(xRange[0]);
      var right = epiweekToDecimal(xRange[1]);
      var ew = (x - marginLeft()) * ((right - left) / getChartWidth()) + left;
      return decimalToEpiweek(ew);
   }
   //y-axis
   function getChartHeight() {
      return canvas.height - marginTop() - marginBottom();
   }
   function getY(i) {
      return (canvas.height - marginBottom()) - (getChartHeight() / (yRange[1] - yRange[0])) * (i - yRange[0]);
   }
   function getIncidence(y) {
      return (-y + (canvas.height - marginBottom())) * ((yRange[1] - yRange[0]) / getChartHeight()) + yRange[0];
   }
   //utilities
   function getGraphics() {
      var g = $('#canvas')[0].getContext('2d');
      //some browsers don't support dashed lines - see http://www.rgraph.net/blog/2013/january/html5-canvas-dashed-lines.html#examples
      if(!g.setLineDash){g.setLineDash = function(x){}}
      return g;
   }
   var Align = {
      left: 0,
      right: 1,
      bottom: 2,
      top: 3,
      center: 4
   };
   function drawText(g, str, x, y, angle, alignX, alignY, scale, font) {
      scale = typeof scale !== 'undefined' ? scale : 1;
      font = typeof font !== 'undefined' ? font : ['', 'Calibri'];
      var size = Math.round(12 * scale * uiScale);
      g.font = font[0] + ' ' + size + 'px ' + font[1];
      var w = g.measureText(str).width;
      var h = size;
      var dx = 0;
      var dy = 0;
      if(alignX == Align.left) {
         dx = 0;
      } else if(alignX == Align.right) {
         dx = -w;
      } else if(alignX == Align.center) {
         dx = -w / 2;
      } else {
         g.strokeStyle = '#ff0000';
      }
      if(alignY == Align.bottom) {
         dy = 0;
      } else if(alignY == Align.top) {
         dy = h;
      } else if(alignY == Align.center) {
         dy = h / 2;
      } else {
         g.strokeStyle = '#ff0000';
      }
      g.save();
      g.translate(x, y);
      g.rotate(angle);
      g.fillText(str, dx, dy);
      g.restore();
      return {x: x + dx, y: y + dy - h, w: w, h: h};
   }
   function drawLine(x1, y1, x2, y2, style) {
      var g = getGraphics();
      g.strokeStyle = style.color;
      g.lineWidth = style.size * uiScale;
      g.setLineDash(style.dash);
      g.beginPath();
      g.moveTo(x1, y1);
      g.lineTo(x2, y2);
      g.stroke();
      g.setLineDash([]);
   }
   function drawCurve(curve, start, end, epiweekOffset, style) {
      var g = getGraphics();
      g.strokeStyle = style.color;
      g.lineWidth = style.size * uiScale;
      g.setLineDash(style.dash);
      g.beginPath();
      var first = true;
      var epiweek = addEpiweeks(xRange[0], epiweekOffset);
      for(var i = start; i < end; i++) {
         if(curve[i] >= 0) {
            var x = getX(epiweek);
            var y = getY(curve[i]);
            if(first) {
               first = false;
               g.moveTo(x, y);
            } else {
               g.lineTo(x, y);
            }
         }
         epiweek = addEpiweeks(epiweek, 1);
      }
      g.stroke();
      g.setLineDash([]);
      if(DRAW_POINTS) {
         g.lineWidth = 3 * style.size * uiScale;
         epiweek = addEpiweeks(xRange[0], epiweekOffset);
         for(var i = start; i < end; i++) {
            if(curve[i] >= 0) {
               g.beginPath();
               var x = getX(epiweek);
               var y = getY(curve[i]);
               g.moveTo(x, y);
               g.lineTo(x + 1, y);
               g.stroke();
            }
            epiweek = addEpiweeks(epiweek, 1);
         }
      }
   }
   function stitchCurves(regionID, style) {
      if(forecast[regionID][0] < 0) {
         return;
      }
      var seasonLength = pastWili[regionID].length - seasonOffsets[seasonOffsets.length - 1];
      var x1 = getX(addEpiweeks(xRange[0], seasonLength - 1));
      var y1 = getY(pastWili[regionID][pastWili[regionID].length - 1]);
      var x2 = getX(addEpiweeks(currentWeek, 1));
      var y2 = getY(forecast[regionID][0]);
      drawLine(x1, y1, x2, y2, style);
   }
   function drawTooltip(g, str) {
      str = ' ' + str;
      var cx = getChartWidth() / 2;
      var cy = getChartHeight() / 2;
      var bt = drawText(g, str, cx, cy, 0, Align.center, Align.center, 1.5);
      var bi = drawText(g, "\uf05a", bt.x, cy, 0, Align.right, Align.center, 1.5, ['', 'FontAwesome']);
      var padding = 6;
      var border = 3;
      g.fillStyle = '#000';
      g.fillRect(bi.x - padding - border, bt.y - padding - border, bi.w + bt.w + 2 * (padding + border), bt.h + 2 * (padding + border));
      g.fillStyle = '#fff';
      g.fillRect(bi.x - padding, bt.y - padding, bi.w + bt.w + 2 * padding, bt.h + 2 * padding);
      g.fillStyle = '#000';
      drawText(g, str, cx, cy, 0, Align.center, Align.center, 1.5);
      drawText(g, "\uf05a", bt.x, cy, 0, Align.right, Align.center, 1.5, ['', 'FontAwesome']);
   }
   function repaint() {
      var g = getGraphics();
      //clear the canvas
      g.clearRect(0, 0, canvas.width, canvas.height);
      g.fillStyle = '#fff';
      g.fillRect(0, 0, canvas.width, canvas.height);
      //past/future
      var weekX = getX(currentWeek + 0.5);
      var x1 = getX(xRange[0]);
      var x2 = getX(xRange[1]);
      var y1 = getY(yRange[0]);
      var y2 = getY(yRange[1]);
      //past
      g.fillStyle = '#eee';
      g.fillRect(x1, y2, weekX - x1, y1 - y2);
      g.fillStyle = '#888';
      drawText(g, '< past', weekX - 15, y2, 0, Align.right, Align.top);
      //future
      g.fillStyle = '#fff';
      g.fillRect(weekX, y2, x2 - weekX, y1 - y2);
      g.fillStyle = '#888';
      drawText(g, 'future >', weekX + 15, y2, 0, Align.left, Align.top);
      //axis styles
      g.lineCap = 'round';
      g.fillStyle = '#000';
      g.lineWidth = 1 * uiScale;
      //y-axis
      {
         var row1 = 12.5 * uiScale;
         var row2 = marginLeft() - 12.5 * uiScale;
         //ticks and lines
         scale_y0 = getY(yRange[0]);
         scale_y1 = getY(yRange[0]+yInterval);
         var scale = scale_y1 - scale_y0
         for(var incidence = yRange[0]; incidence <= yRange[1]; incidence += yInterval) {
            var y = getY(incidence);
            drawText(g, '' + incidence, row2, y, 0, Align.right, Align.center);
            drawLine(marginLeft() - TICK_SIZE, y, marginLeft() - 1, y, AXIS_STYLE);
            drawLine(getX(xRange[0]), y, getX(xRange[1]), y, GRID_STYLE);
         }
         //label
         drawText(g, LABEL_Y, row1, canvas.height / 2, -Math.PI / 2, Align.center, Align.center, 1.5, ['bold', 'Calibri']);
         //zoom controls
         var x = 16 * uiScale;
         var dy = BUTTON_SIZE * uiScale;
         zoomUpBounds = drawText(g, "\uf151", x, y2, 0, Align.center, Align.top, 2, ['', 'FontAwesome']);
         zoomDownBounds = drawText(g, "\uf150", x, y2 + dy, 0, Align.center, Align.top, 2, ['', 'FontAwesome']);
      }
      //x-axis
      {
         var row1 = 0.75 * (marginBottom() / 3);
         var row2 = 1.75 * (marginBottom() / 3);
         var row3 = 2.5 * (marginBottom() / 3);
         var axisY = canvas.height - marginBottom();
         //flu season
         //ticks
         var skip = 0;
         for(var epiweek = xRange[0]; epiweek <= xRange[1]; epiweek = addEpiweeks(epiweek, 1)) {
            var x = getX(epiweek);
            if(skip == 0) {
               drawText(g, '' + (epiweek % 100), x, canvas.height - row3, 0, Align.center, Align.center);
            }
            skip = (skip + 1) % xInterval;
            drawLine(x, axisY + TICK_SIZE, x, axisY + 1, AXIS_STYLE);
         }
         //months
         var month = Math.floor((xRange[0] % 100 - 1) / getNumWeeks(Math.floor(xRange[0] / 100)) * MONTHS.length);
         for(var epiweek = xRange[0]; epiweek <= xRange[1]; epiweek = addEpiweeks(epiweek, 4.35)) {
            var label = MONTHS[month];
            if(month == 0) {
               label += '\'' + (Math.floor(epiweek / 100) % 100);
            }
            drawText(g, label, getX(epiweek), canvas.height - row2, 0, Align.center, Align.center);
            month = (month + 1) % MONTHS.length;
         }
         //label
         drawText(g, LABEL_X, canvas.width / 2, canvas.height - row1, 0, Align.center, Align.center, 1.5, ['bold', 'Calibri']);
      }
      //other regions or past seasons
      for(var i = 0; i < selectedSeasons.length; i++) {
         var isCurrentSeason = (selectedSeasons[i][1] == seasonYears[seasonYears.length - 1]);
         if(selectedSeasons[i][0] == regionID && isCurrentSeason) {
            //Skip the current region's latest season
            continue;
         }
         var r = selectedSeasons[i][0];
         var s = selectedSeasons[i][1];
         var style = curveStyles[r][s];
         var start = seasonOffsets[seasonIndices[s]];
         var length = totalWeeks;
         <?php if(!$showPandemic) { ?>if(s == 2008) { length -= 12; }<?php } ?>
         var epiweekOffset = 0;
         if(start == 0) {
            var nextStart = seasonOffsets[seasonIndices[s + 1]];
            length = nextStart - start;
            //todo: that -1 at the end should only be there if current season has 53 weeks and past season has 52 weeks
            epiweekOffset = Math.max(0, totalWeeks - length - 1);
         }
         var end = Math.min(pastWili[r].length, start + length);
         drawCurve(pastWili[r], start, end, epiweekOffset, style);
         if(isCurrentSeason) {
            style = {color: style.color, size: style.size, dash: DASH_STYLE};
            drawCurve(forecast[r], 0, 52, numPastWeeks + 1, style);
            stitchCurves(r, style);
         }
      }
      //last forecast
      var lfStyle = {color: '#aaa', size: 2, dash: DASH_STYLE};
      if(showLastForecast) {
         drawCurve(lastForecast, 0, lastForecast.length, totalWeeks - lastForecast.length, lfStyle);
      }
      //current region and latest season
      var style = {color: '#000', size: 2, dash: []};
      var start = seasonOffsets[seasonOffsets.length - 1];
      var end = Math.min(pastWili[regionID].length, start + totalWeeks);
      drawCurve(pastWili[regionID], start, end, 0, style);
      style.dash = DASH_STYLE;
      drawCurve(forecast[regionID], 0, 52, numPastWeeks + 1, style);
      stitchCurves(regionID, style);
      //nowcast
      if(showNowcast) {
         g.fillStyle = 'rgba(0, 0, 0, 0.5)';
         var epiweek = addEpiweeks(xRange[0], numPastWeeks + 1);
         var x = getX(epiweek);
         var y1 = getY(nowcast[0] - 2 * nowcast[1]);
         var y2 = getY(nowcast[0] + 2 * nowcast[1]);
         g.fillRect(x - 2, y1, 5, y2 - y1);
         y1 = getY(nowcast[0] - 1 * nowcast[1]);
         y2 = getY(nowcast[0] + 1 * nowcast[1]);
         g.fillRect(x - 4, y1, 9, y2 - y1);
         y1 = getY(nowcast[0]);
         g.fillRect(x - 5, y1, 11, 1);
      }
      g.fillStyle = 'rgba(0, 0, 0, 0.5)';
      // var epiweek = addEpiweeks(xRange[0], numPastWeeks + 1);
      // var errors = [[-0.25421423, 0.26941263, -0.15824849, 0.20264450, -0.12278384, 0.15011437, -0.11155995, 0.13835847, -0.10352876, 0.12303700],
      //               [-0.35126740, 0.28674480, -0.18549541, 0.22947093, -0.12160618, 0.16089217, -0.12108624,0.14741964, -0.09037853, 0.13066419],
      //               [-0.53753338, 0.92758200, -0.31119984, 0.66195073, -0.14422368, 0.58243822, -0.13170999, 0.50501000, -0.07412529, 0.45190200],
      //               [-0.39443287, 0.41650200, -0.28787902, 0.17568074, -0.23393490, 0.13345493, -0.18385105, 0.10137001, -0.16447600, 0.08822047],
      //               [-0.20688904, 0.31740226, -0.11901200, 0.25532236, -0.08398740, 0.22726787, -0.07123000, 0.21431023, -0.06658800, 0.21996389],
      //               [-0.24942400, 0.20380060, -0.13837004, 0.13287000, -0.12956857, 0.11119205, -0.12638667, 0.09339000, -0.12312460, 0.09194342],
      //               [-0.58407168, 0.65345295, -0.28553676, 0.45182749, -0.20596358, 0.43264114, -0.19105741, 0.41678847, -0.19351715, 0.41257600],
      //               [-0.33326599, 0.53635165, -0.30726399, 0.25629324, -0.19375001, 0.17594002, -0.11035500, 0.13316077, -0.07146323, 0.09787316],
      //               [-0.35295015, 0.15924199, -0.31480422, 0.11476599, -0.29572400, 0.08918193, -0.27731600, 0.07671607, -0.27938700, 0.08092397],
      //               [-1.36375200, 0.36499200, -0.84292035, 0.35217596, -0.61532002, 0.30050214, -0.25313998, 0.27465654, -0.22830517, 0.24405587],
      //               [-0.30122761, 0.69179569, -0.13852871, 0.54593001, -0.08857343, 0.44755800, -0.08709004, 0.39676995, -0.06306197, 0.33658196]]
      //
      // if (regionID <= 11) {
      //    var error = errors[regionID-1]
      //    for (var i=0; i<9; i = i + 2) {
      //       var above = -error[i]*scale;
      //       var below = error[i+1]*scale;
      //       var x = getX(epiweek-(i/2)-1);
      //       var y = getY(pastWili[regionID][pastWili[regionID].length - i/2 - 1]);
      //       g.fillRect(x-2.5, y-above, 5, above);
      //       g.fillRect(x-2.5, y, 5, below);
      //    }
      // }

      //legend
      var x1 = canvas.width - marginRight();
      var x2 = canvas.width - marginRight() - (15 * uiScale);
      var dy = 12 * uiScale;
      var y = marginTop() + dy;
      var labelBounds = drawText(g, 'Your Forecast, Last Week', x2 - 3, y, 0, Align.right, Align.center);
      drawLine(x1, y - 3, x2, y + 3, lfStyle);
      g.fillStyle = '#000';
      showLastBounds = drawText(g, showLastForecast ? "\uf046" : "\uf096", labelBounds.x - 5 * uiScale, y, 0, Align.right, Align.center, 1.25, ['', 'FontAwesome']);
      snapLastBounds = drawText(g, "\uf08d", showLastBounds.x - 5 * uiScale, y, 0, Align.right, Align.center, 1.25, ['', 'FontAwesome']);
      y += dy;
      drawText(g, 'Your Forecast, This Week', x2 - 3, y, 0, Align.right, Align.center);
      drawLine(x1, y - 3, x2, y + 3, style);
      y += dy;
      drawText(g, regionNames[regionID] + ', ' + Math.round(xRange[0] / 100) + '+', x2 - 3, y, 0, Align.right, Align.center);
      style.dash = [];
      drawLine(x1, y - 3, x2, y + 3, style);
      for(var i = 0; i < selectedSeasons.length; i++) {
         y += dy;
         var r = selectedSeasons[i][0];
         var s = selectedSeasons[i][1];
         var style = curveStyles[r][s];
         drawText(g, regionNames[r] + ', ' + s + '+', x2 - 3, y, 0, Align.right, Align.center);
         drawLine(x1, y - 3, x2, y + 3, style);
      }

      // error bars
      drawText(g, '90% Confidence Interval', x2 - 3, y+25, 0, Align.right, Align.center);
      g.fillStyle = 'rgba(0, 0, 0, 0.5)';
      g.fillRect(x2+5, y+10, 5, 35);

      //tooltip
      if(tooltip != null) {
         drawTooltip(g, tooltip);
      }
   }
   //more utilities
   function getNumWeeks(year) {
      return (year == 1997 || year == 2003 || year == 2008 || year == 2014) ? 53 : 52;
   }
   function getDeltaWeeks(start, end) {
      var x = (end > start) ? 1 : -1;
      var num = 0;
      while(start != end && num < 1e3) {
         start = addEpiweeks(start, x);
         num += x;
      }
      return num;
   }
   function addEpiweeks(ew, i) {
      var year = Math.floor(ew / 100);
      var week = ew % 100;
      week += i;
      var limit = getNumWeeks(year);
      if(week >= limit + 1) {
         week -= limit;
         year += 1;
      } else if(week < 1) {
         week += getNumWeeks(year - 1);
         year -= 1;
      }
      return year * 100 + week;
   }
   function epiweekToDecimal(ew) {
      var year = Math.floor(ew / 100);
      var week = ew % 100;
      return year + (week - 1) / getNumWeeks(year);
   }
   function decimalToEpiweek(yr) {
      yr += 0.5 / 52;
      var year = Math.floor(yr);
      var wk = yr - year;
      var week = Math.floor(wk * getNumWeeks(year)) + 1;
      return year * 100 + week;
   }
   function animate() {
      repaint();
      if(dragging) {
         requestAnimationFrame(animate);
      } else {
         repaint();
      }
   }
   function adjustForecast(x, y) {
      var epiweek = getEpiweek(x);
      if(epiweek > currentWeek && epiweek <= xRange[1]) {
         var wili = Math.min(yRange[1], Math.max(yRange[0], getIncidence(y)));
         forecast[regionID][getDeltaWeeks(currentWeek, epiweek) - 1] = wili;
         if(lastDrag != null && epiweek != lastDrag.epiweek) {
            var direction = (epiweek > lastDrag.epiweek) ? 1 : -1;
            for(var i = addEpiweeks(lastDrag.epiweek, direction); i != epiweek; i = addEpiweeks(i, direction)) {
               forecast[regionID][getDeltaWeeks(currentWeek, i) - 1] = wili;
            }
         }
         lastDrag = {epiweek: epiweek, wili: wili};
         modified = true;
      } else {
         lastDrag = null;
      }
   }
   function contains(bounds, point) {
      var x1 = bounds.x;
      var x2 = bounds.x + bounds.w;
      var y1 = bounds.y;
      var y2 = bounds.y + bounds.h;
      return (point.x >= x1 && point.x <= x2 && point.y >= y1 && point.y <= y2);
   }
   //user interaction
   function mouseDown(m) {
      tooltip = null;
      if(contains(zoomUpBounds, m)) {
         zoom(1 / ZOOM_AMOUNT);
      } else if(contains(zoomDownBounds, m)) {
         zoom(ZOOM_AMOUNT);
      //} else if(contains(undoBounds, m)) {
      //   undo();
      //} else if(contains(redoBounds, m)) {
      //   redo();
      } else if(contains(showLastBounds, m)) {
         showLastForecast = !showLastForecast;
         repaint();
      } else if(contains(snapLastBounds, m)) {
         if(confirm('Are you sure you want to reset your current forecast to your previous forecast?')) {
            snapToLastForecast();
         }
      } else {
         $('#canvas').addClass('canvas_drag');
         adjustForecast(m.x, m.y);
         dragging = true;
         animate();
      }
   }
   function mouseUp(m) {
      $('#canvas').removeClass('canvas_drag');
      if(dragging) {
         dragging = false;
         lastDrag = null;
         if(modified) {
            ++modifyCounter;
            setTimeout(submitForecastDelayed, AUTOSAVE_INTERVAL * 1000);
         }
         modified = false;
      }
   }
   function mouseMove(m) {
      //Drawing a forecast
      if(dragging) {
         adjustForecast(m.x, m.y);
         return;
      }
      //Interacting with a button
      var buttons = [
         {
            bounds: zoomUpBounds,
            tooltip: 'Decrease the scale of the Y axis. (Zoom in.)',
         },{
            bounds: zoomDownBounds,
            tooltip: 'Increase the scale of the Y axis. (Zoom out.)',
         },{
            bounds: showLastBounds,
            tooltip: 'Show or hide your forecast from last week.',
         },{
            bounds: snapLastBounds,
            tooltip: 'Pin your current forecast to your forecast from last week.',
         },
      ];
      //Find out which button (if any)
      var hb = null;
      tooltip = null;
      for(var i = 0; i < buttons.length; i++) {
         if(contains(buttons[i].bounds, m)) {
            hb = buttons[i].bounds;
            tooltip = buttons[i].tooltip;
            break;
         }
      }
      //Update if the hovered button has changed
      if(hoveringButton != hb) {
         if(hoveringButton != null && hb == null) {
            //back to the normal cursor
            $('#canvas').removeClass('canvas_button');
         } else if(hoveringButton == null && hb != null) {
            //use the button cursor
            $('#canvas').addClass('canvas_button');
         }
         hoveringButton = hb;
         repaint();
      }
   }
   function mousePosition(e) {
      if(e.type.toLowerCase().indexOf('touch') == 0) {
         e = e.originalEvent.changedTouches[0];
      }
      var canvas = $('#canvas');
      return {
         x: e.pageX - canvas.offset().left,
         y: e.pageY - canvas.offset().top
      };
   }
   function zoom(scale) {
      yRange[1] = Math.min(WILI_MAX, Math.max(WILI_MIN, yRange[1] * scale));
      repaint();
   }
   function submitForecastDelayed() {
      ++submitCounter;
      if(modifyCounter == submitCounter && !dragging) {
         //No modifications in the last AUTOSAVE_INTERVAL seconds
         submitForecast(false);
      }
   }
   function submitForecast(commit) {
      if(commit && $('#button_submit').hasClass('box_button_disabled')) {
         return;
      }
      var foundZero = false;
      var f = [];
      for(var i = 0; i < 52; i++) {
         f[i] = forecast[regionID][i];
         foundZero |= f[i] == 0;
      }
      if(commit) {
         if(foundZero) {
            alert('Some points are still at zero. Please double check your forecast and try again.');
            return;
         }
         timeoutID = setTimeout(submitTimeout, 20000);
         submitStatus = SubmitStatus.sent;
         updateStatus();
         $('#button_submit').addClass('box_button_disabled');
      }
      var params = {
         'action': commit ? 'forecast' : 'autosave',
         'mturkID': "<?= $mturkID ?>",
         'hash': "<?= "147d9191" ?>",
         'userID': "<?= $userID ?>",
         'region_id': regionID,
         'f[]': f,
      };
      console.log("inside submitForecast, sending params");
      console.log(params);
      console.log("in api_mturk.php, calling handleResponse");
      $.get("api_mturk.php", params, handleResponse, 'json');
      console.log("sent");
   }

   function updateStatus() {
      $('#box_status').removeClass('any_success any_failure any_neutral');
      if(submitStatus == SubmitStatus.sent) {
         $('#status_icon').html('<i class="fa fa-cog fa-spin"></i>');
         $('#status_message').html('Uploading forecast...');
         $('#box_status').addClass('any_neutral');
      } else if(submitStatus == SubmitStatus.success) {
         $('#status_icon').html('<i class="fa fa-check-circle"></i>');
         $('#status_message').html('Forecast submitted successfully!');
         $('#box_status').addClass('any_success');
         //Move to the next missing region, or go home
         <?php
         $next = null;
         $defaultNumRegion = 16;
         $currentID = $region['id'];
         if ($currentID <= $defaultNumRegion) {

            for ($i = 20; $i <= 20 + $defaultNumRegion; $i++) {
            $r = $output['regions'][$i];
            if($r['id'] > $region['id'] && !$r['completed'] && $next === null) {
               $next = $r['id'];
            }
         }

            for ($i = 20; $i <= 20 + $defaultNumRegion; $i++) {
               $r = $output['regions'][$i];
               if($r['id'] < $region['id'] && !$r['completed'] && $next === null) {
                  $next = $r['id'];
               }
            }
         }


         if($next !== null) {
            ?>
            console.log(<?= $next ?>);
            redirect('forecast_mturk.php?id=<?= $next ?>');
            <?php
         } else {
            ?>
            redirect('endOfSurvey.php');
            <?php
         }
         ?>
      } else if(submitStatus == SubmitStatus.failure) {
         $('#status_icon').html('<i class="fa fa-times-circle"></i>');
         $('#status_message').html('Uh oh, something went wrong. Please try again later.');
         $('#box_status').addClass('any_failure');
      }
   }

   //other events
   function submitTimeout() {
      console.log("inside submitTimeout, calling handleResponse");
      handleResponse({result: 0, action: 'forecast'});
      console.log("handleResponse called");
   }

   function handleResponse(data) {
       console.log('inside handleResponse');
       console.log(data);
      if(data.action != 'forecast') {
         //don't really care what the result was unless it has to do with the submit forecast button
         return;
      }
      clearTimeout(timeoutID);
      //$('#stat_completed').removeClass();
      $('#button_submit').removeClass('box_button_disabled');
      if(data.result == 1) {
         //$('#stat_completed').addClass('any_success');
         //$('#stat_completed').html('Submitted');
         submitStatus = SubmitStatus.success;
      } else {
         submitStatus = SubmitStatus.failure;
      }
      updateStatus();
   }

   function resize() {
      //Find the right fit for the canvas
      var w = $('body').innerWidth() - $('#box_histories').width() - 48;
      var h = $(window).height();
      w = Math.floor(w - 24);
      h = Math.floor((h - (56 + 24 + 47 + 24 + 33)) * 0.98);
      //Get the drawing scale
      uiScale = ((w * 2 + h * 1) / 3) / 1000;
      //Apply the resize
      canvas.width = w;
      canvas.height = h;
      $('#box_canvas').width(w);
      $('#box_canvas').height(h);
      $('#box_side_bar').height(h);
      $('#box_histories').height(h - 8);
      //Finally, repaint the canvas
      repaint();
   }
   function toggleSeasonList(regionID) {
      var closedClass = 'fa-plus-square-o';
      var openedClass = 'fa-minus-square-o';
      var checkbox = $('#checkbox_region_' + regionID);
      if(checkbox.hasClass(closedClass)) {
         //Expand region
         checkbox.removeClass(closedClass);
         checkbox.addClass(openedClass);
         $('#container_' + regionID + '_all').removeClass('any_hidden');
         for(var i = 0; i < seasonYears.length; i++) {
            $('#container_' + regionID + '_' + seasonYears[i]).removeClass('any_hidden');
         }
      } else {
         //Shrink region
         checkbox.removeClass(openedClass);
         checkbox.addClass(closedClass);
         $('#container_' + regionID + '_all').addClass('any_hidden');
         for(var i = 0; i < seasonYears.length; i++) {
            $('#container_' + regionID + '_' + seasonYears[i]).addClass('any_hidden');
         }
      }
      repaint();
   }

   function toggleAllSeasons(regionID) {
      var uncheckedClass = 'fa-square-o';
      var checkedClass = 'fa-check-square-o';
      var checkbox = $('#checkbox_' + regionID + '_all');
      if(checkbox.hasClass(uncheckedClass)) {
         //Enable history
         checkbox.removeClass(uncheckedClass);
         checkbox.addClass(checkedClass);
         for(var i = 0; i < seasonYears.length; i++) {
            if($('#checkbox_' + regionID + '_' + seasonYears[i]).hasClass(uncheckedClass)) {
               toggleSeason(regionID, seasonYears[i]);
            }
         }
      } else {
         //Disable history
         checkbox.removeClass(checkedClass);
         checkbox.addClass(uncheckedClass);
         for(var i = 0; i < seasonYears.length; i++) {
            if($('#checkbox_' + regionID + '_' + seasonYears[i]).hasClass(checkedClass)) {
               toggleSeason(regionID, seasonYears[i]);
            }
         }
      }
      repaint();
   }
   function toggleSeason(regionID, seasonID) {
      var uncheckedClass = 'fa-square-o';
      var checkedClass = 'fa-check-square-o';
      var checkbox = $('#checkbox_' + regionID + '_' + seasonID);
      if(checkbox.hasClass(uncheckedClass)) {
         //Enable history
         checkbox.removeClass(uncheckedClass);
         checkbox.addClass(checkedClass);
         selectedSeasons.push([regionID, seasonID]);
      } else {
         //Disable history
         checkbox.removeClass(checkedClass);
         checkbox.addClass(uncheckedClass);
         var index = -1;
         for(var i = 0; i < selectedSeasons.length; i++) {
            if(selectedSeasons[i][0] == regionID && selectedSeasons[i][1] == seasonID) {
               index = i;
               break;
            }
         }
         if(index > -1) {
            selectedSeasons.splice(index, 1);
         }
      }
      repaint();
   }
   function snapToLastForecast() {
      var extra = lastForecast.length - forecast[regionID].length;
      for(var i = 0; i < Math.min(forecast[regionID].length, lastForecast.length - extra); i++) {
         forecast[regionID][i] = lastForecast[i + extra];
      }
      repaint();
      ++modifyCounter;
      setTimeout(submitForecastDelayed, AUTOSAVE_INTERVAL * 1000);
      modified = false;
   }
   //main
   $(document).ready(function() {
      var canvas = $('#canvas');
      canvas.on('mousedown touchstart', function(e) { e.preventDefault(); mouseDown(mousePosition(e)); });
      canvas.on('mouseup mouseout touchend touchleave touchcancel', function(e) { e.preventDefault(); mouseUp(mousePosition(e)); });
      canvas.on('mousemove touchmove', function(e) { e.preventDefault(); mouseMove(mousePosition(e)); });
      $(window).resize(function() {
         resize();
      });
      toggleSeasonList(regionID);
      <?php

      // all seasons are shown by default, so hide the ones the user doesn't want to see
      $hiddenSeasons = getPreference($output, 'hidden_seasons', 'int');
      // toggle every season that has the "hide" bit set
      for($season = 1997; $season < $current_season; $season++) {
         if(($hiddenSeasons & 1) === 0) {
            ?>toggleSeason(regionID, <?= $season ?>);<?php
         }
         $hiddenSeasons >>= 1;
      }

      ?>
      resize();
   });
</script>

<?php
}
require_once('common/footer.php');
?>
