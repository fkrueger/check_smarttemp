<?php

/*
 *   (c) 2013,2017 by Frederic Krueger / fkrueger-dev-checksmarttemp@holics.at
 *    
 *   Licensed under the Apache License, Version 2.0
 *   There is no warranty of any kind, explicit or implied, for anything this software does or does not do.
 *
 *   Updates for this piece of software could be available under the following URL:
 *     GIT: https://github.com/fkrueger-2/check_smarttemp
 *     Home: http://dev.techno.holics.at/check_smarttemp/
 *     
 *   Requires: pnp4nagios
 *
 *   On my testrig, this graph template looks good in "nice" mode  most of the time; only really works nicely
 *   on data with linear distances between the datasources' datapoints though. It still beats the default template, IMO.
 * 
 */


$graphtype = "nice";  # nice or boring
$coloring = "nice";   # nice or boring, see definitions right below

$DEBUG = 0;


## init
$opt[1] =  " --title \"SMART temperatures for " . $this->MACRO['DISP_HOSTNAME'] . ' / ' . $this->MACRO['DISP_SERVICEDESC'] . "\" ";
$opt[1] .= " --font DEFAULT:7: --slope-mode ";
$def[1] = "";

## the coloring
$nicercolors = array (
 'dark' => array(
  'cc3118', 'cc7016', 'c9b215',  # red orange yellow
  '24bc14', '1598c3', 'b415c7',  # green blue pink
  '4d18e4',                      # purple
  'cc3118', 'cc7016', 'c9b215',  # red orange yellow
  '24bc14', '1598c3', 'b415c7',  # green blue pink
  '4d18e4'                       # purple
 ),
 'light' => array(
  'ea644a', 'ec9d48', 'ecd748',  # red orange yellow
  '54ec48', '48c4ec', 'de48ec',  # green blue pink
  '7648ec',                      # purple
  'ea644a', 'ec9d48', 'ecd748',  # red orange yellow
  '54ec48', '48c4ec', 'de48ec',  # green blue pink
  '7648ec'                       # purple
 )
);

$boringcolors = array(
  'dark' => array(
    '000000', '222222', '444444', '666666', '888888', 'aaaaaa', 'cccccc', 'eeeeee'
  ),
  'light' => array(
    '111111', '333333', '555555', '777777', '999999', 'bbbbbb', 'dddddd', 'ffffff'
  )
);


## main
$usedcolors = ($coloring == "nice" ? $nicercolors : $boringcolors);

if ($graphtype == "nice")
{
  $def[1] .= nice_graph ($this->DS, "temp", $usedcolors);
}

else

{
  $def[1] .= dflt_graph ($this->DS, "temp", $usedcolors);
}

## fin.



function nice_graph ($cur_ds, $dsprefix = "temp", $usedcolors = array())
{
  global $DEBUG;

  $dsprefixlc = strtolower($dsprefix);

  $ds_data = array();      # assoc
  $ds_keynames = array();  # "DP<num>" => array(key,val)
  $ds_names = array();     # assoc val['NAME'] => "DP<num>"

  $tmpdef = "";   # which we return once we re done here

  # 1. collect names and values
  $linecnt=1;
  reset ($cur_ds);
  foreach ($cur_ds as $key => $val)
  {
    if (! isset($ds_data[ $val['NAME'] ]))  # key = number (0..99), without leading zeroes
    {
      if ((strpos(strtolower($val['NAME']), $dsprefixlc) !== false) and (strpos(strtolower($val['NAME']), $dsprefixlc) == 0))
      {
        # $tmpdef .= rrd::cdef ("NewKey$key", print_r($val, true));
#        $tmpdef .= "inputds_$key: " .$val['NAME']. "  ";
        $ds_data[ sprintf("%04d##%s", $val['ACT'], $val['NAME']) ] = array( 'key' => $key, 'val' => $val );
      }
    } # end if new key gotten
    else
    {
#      $tmpdef .= rrd::cdef ("DupKey$key", $val['NAME']);
      $tmpdef .= "DupKey$key: " .$val['NAME']. "  ";
    } # end if dupe key gotten

    $linecnt++;
  } # end foreach name/value collector

  $lastds=$linecnt-1; # ds_data goes from 1..$lastds
#  $tmpdef .= "lastds: $lastds  ";
#  $tmpdef .= "lastds: $lastds  ";

  # 1.5 get sort order by ACT value
  

  # 2. sort data in reverse alphabetical order
  reset ($ds_data); ksort ($ds_data);

  # 3. get keynames at current position for later referencing
  $linecnt = 1;
  foreach ($ds_data as $k => $v)   # go from back to front because of the nature of traceroute and pings
  {
    $tmpdef .= "DEF:DP$linecnt=" .$v['val']['RRDFILE']. ":" .$v['val']['DS']. ":AVERAGE  ";
    $ds_keynames["DP$linecnt"] = $k;
    $ds_names[$v['val']['NAME']] = "DP$linecnt";
    $linecnt++;
  } # end foreach key in order

  # 4. draw stacking lines
  for ($xx = 1; $xx <= $lastds; $xx++)
  {
    $curk = $ds_keynames["DP$xx"];

    $s = "DP$xx";
    $tmpdef .= sprintf ("CDEF:%s%s=%s", $ds_data [ $ds_keynames["DP$xx"] ]['val']['NAME'], ($DEBUG?"--".$ds_keynames["DP$xx"]:""), $s). "  ";
  } # end foreach ds from back to front

  # then draw areas 
  for ($xx = $lastds; $xx >= 1; $xx--)
  {
    $s = "";
#    $s = (($xx > 1) ? ":STACK" : "");

    # get a fine label
    $curprek = "DP${xx}";
    if (isset($ds_keynames[$curprek]))
    {
      $curk = $ds_keynames[$curprek];
      $prtname = $ds_data[$curk]['val']['NAME'];
    }
    else
    {
      $prtname = "${dsprefix}_$xx";
    }
    $prtname = preg_replace ("/_/", " ", $prtname);

    # print it
    $tmpdef .= sprintf ("AREA:%s#%s:\"%s\"%s", $ds_data [ $ds_keynames["DP$xx"] ]['val']['NAME'], $usedcolors['light'][$xx-1], $prtname, $s). "  ";
  }
  
  # then overlay with lines of darker colors
  for ($xx = 1; $xx <= $lastds; $xx++)
  {
    $tmpdef .= sprintf ("LINE1:%s#%s", $ds_data [ $ds_keynames["DP$xx"] ]['val']['NAME'], $usedcolors['dark'][$xx-1]). "  ";
  }

  # and add a legend (gprint odd entries first, then even entries)
  $tmpdef .= "COMMENT:\"\\c\"  ";
  $tmpdef .= "COMMENT:\"\\r\"  ";
  $yy = 0;
#  while ($yy++ <= 1)
  {
    for ($xx = 1; $xx <= $lastds; $xx++)
    {
      $curprek = "DP" .($xx-$yy);
      if (isset($ds_keynames[$curprek]))
      {
        $curk = $ds_keynames[$curprek];
        $prtname = $ds_data[$curk]['val']['NAME'];
        $prtname = preg_replace ("/_/", " ", $prtname);
        $dsname = $ds_data [ $ds_keynames["DP$xx"] ]['val']['NAME'];

        $tmpdef .= "GPRINT:$dsname:LAST:\"$prtname   Cur %5.2lf°C\" ";
        $tmpdef .= "GPRINT:$dsname:MIN:\" Min Avg Max  %5.2lf°C\" ";
        $tmpdef .= "GPRINT:$dsname:AVERAGE:\"%5.2lf°C\" ";
        $tmpdef .= "GPRINT:$dsname:MAX:\"%5.2lf°C\\c\" ";
      }
    }
  }

  $tmpdef .= "COMMENT:\"\\r\"  ";
  $tmpdef .= "COMMENT:\"Command " . $val['TEMPLATE'] . " (template\: nice_graph)\\r\"  ";

  return ($tmpdef);
} # end func nice_graph



function dflt_graph ($cur_ds, $dsprefix = "temp", $usedcolors = array())
{
  global $DEBUG;
  $tmpdef = "";

  $linecnt=1;
  foreach ($cur_ds as $key => $val)
  {
    if ((strpos($val['NAME'], $dsprefix) !== false) and (strpos($val['NAME'], $dsprefix) >= 0))
    {
      $tmpdef .= rrd::def     ("var$key", $val['RRDFILE'], $val['DS'], "AVERAGE");
      $tmpdef .= rrd::line1   ("var$key", "#" .$usedcolors['dark'][$linecnt], rrd::cut($val['NAME'],22) );
      $tmpdef .= rrd::gprint  ("var$key", array("LAST","AVERAGE"), "%9.4lf %S".$val['UNIT']);
      $linecnt++;
    }
  }

  $tmpdef .= rrd::comment("\\r");
  $tmpdef .= rrd::comment("Command " . $val['TEMPLATE'] . " (template\: dflt_graph)\\r");

  return ($tmpdef);
} # end func dflt_graph


?>
