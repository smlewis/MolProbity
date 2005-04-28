<?php # (jEdit options) :folding=explicit:collapseFolds=1:
/*****************************************************************************
    Provides functions for producing analysis data from outside programs
    and for loading and interpretting that data.
    
    Many functions work with a column-formatted residue name
    stored in exactly 9 characters, like this: 'cnnnnittt'
        c: Chain ID, space for none
        n: sequence number, right justified, space padded
        i: insertion code, space for none
        t: residue type (ALA, LYS, etc.), all caps, left justified, space padded
*****************************************************************************/
require_once(MP_BASE_DIR.'/lib/strings.php');
require_once(MP_BASE_DIR.'/lib/visualize.php'); // for making kinemages

#{{{ runAnalysis - generate (a subset of) all the validation criteria
############################################################################
/**
* This is the uber-validation function that calls everything below.
* It is suited for use from either the web or command line interface.
* This only makes sense in terms of an active session.
*   modelID             ID code for model to process
*   opts                has the following keys mapped to boolean flags:
*       doAAC           run clashlist and get clashscore
*                       run Probe to make dot kins (part of multicrit kin)
*       showHbonds      whether to make H-bond dots
*       showContacts    whether to make vdW dots
*       doRama          run Rama eval and make plots
*       doRota          run rotamer eval and make (list?)
*       doCbDev         calc Cbeta deviations and make kins
*       doBaseP         calc base-phosphate perpendiculars and make (chart?)
*       doSummaryStats  make table of summary statistics
*       doMultiKin      make multicrit kinemage
*       multiKinExtras  include ribbons, B's and Q's, and alt confs
*       doMultiChart    make multicrit chart
*       multiChartSort  how to sort the chart [ see makeMulticritChart() ]
*       
* This function returns some HTML suitable for using in a lab notebook entry.
*/
function runAnalysis($modelID, $opts)
{
    //{{{ Set up file/directory vars and the task list
    $model      = $_SESSION['models'][$modelID];
    $modelDir   = $_SESSION['dataDir'].'/'.MP_DIR_MODELS;
    $kinDir     = $_SESSION['dataDir'].'/'.MP_DIR_KINS;
    $kinURL     = $_SESSION['dataURL'].'/'.MP_DIR_KINS;
        if(!file_exists($kinDir)) mkdir($kinDir, 0777);
    $rawDir     = $_SESSION['dataDir'].'/'.MP_DIR_RAWDATA;
        if(!file_exists($rawDir)) mkdir($rawDir, 0777);
    $chartDir   = $_SESSION['dataDir'].'/'.MP_DIR_CHARTS;
    $chartURL   = $_SESSION['dataURL'].'/'.MP_DIR_CHARTS;
        if(!file_exists($chartDir)) mkdir($chartDir, 0777);
    $infile     = "$modelDir/$model[pdb]";
    
    if($opts['doRama'])         $tasks['rama'] = "Do Ramachandran analysis and make plots";
    if($opts['doRota'])         $tasks['rota'] = "Do rotamer analysis";
    if($opts['doCbDev'])        $tasks['cbeta'] = "Do C&beta; deviation analysis and make kins";
    
    $runBaseP = $opts['doBaseP'] && ($opts['doSummaryStats'] || $opts['doMultiChart']);
    if($runBaseP)               $tasks['base-phos'] = "Do base-phosphate perpendicular analysis";
    
    $runClashlist = $opts['doAAC'] && ($opts['doSummaryStats'] || $opts['doMultiChart']);
    if($runClashlist)           $tasks['clashlist'] = "Run <code>clashlist</code> to find bad clashes and clashscore";
    if($opts['doMultiChart'])   $tasks['multichart'] = "Create multi-criterion chart";
    if($opts['doMultiKin'])     $tasks['multikin'] = "Create multi-criterion kinemage";
    //}}} Set up file/directory vars and the task list
    
    //{{{ Run protein geometry programs and offer kins to user
    // Ramachandran
    if($opts['doRama'])
    {
        setProgress($tasks, 'rama'); // updates the progress display if running as a background job
        $outfile = "$rawDir/$model[prefix]rama.data";
        runRamachandran($infile, $outfile);
        $rama = loadRamachandran($outfile);
        
        makeRamachandranKin($infile, "$kinDir/$model[prefix]rama.kin");
        $tasks['rama'] .= " - preview <a href='viewking.php?$_SESSION[sessTag]&url=$kinURL/$model[prefix]rama.kin' target='_blank'>kinemage</a>";
        setProgress($tasks, 'rama'); // so the preview link is visible
        makeRamachandranPDF($infile, "$chartDir/$model[prefix]rama.pdf");
        $tasks['rama'] .= " | <a href='$chartURL/$model[prefix]rama.pdf' target='_blank'>PDF</a>\n";
        setProgress($tasks, 'rama'); // so the preview link is visible
    }
    
    // Rotamers
    if($opts['doRota'])
    {
        setProgress($tasks, 'rota'); // updates the progress display if running as a background job
        $outfile = "$rawDir/$model[prefix]rota.data";
        runRotamer($infile, $outfile);
        $rota = loadRotamer($outfile);
    }
    
    // C-beta deviations
    if($opts['doCbDev'])
    {
        setProgress($tasks, 'cbeta'); // updates the progress display if running as a background job
        $outfile = "$rawDir/$model[prefix]cbdev.data";
        runCbetaDev($infile, $outfile);
        $cbdev = loadCbetaDev($outfile);
        
        makeCbetaDevPlot($infile, "$kinDir/$model[prefix]cb2d.kin");
        $tasks['cbeta'] .= " - <a href='viewking.php?$_SESSION[sessTag]&url=$kinURL/$model[prefix]cb2d.kin' target='_blank'>preview</a>";
        setProgress($tasks, 'cbeta'); // so the preview link is visible
    }
    //}}} Run programs and offer kins to user
    
    //{{{ Run nucleic acid geometry programs and offer kins to user
    // Base-phosphate perpendiculars
    if($runBaseP)
    {
        setProgress($tasks, 'base-phos'); // updates the progress display if running as a background job
        $outfile = "$rawDir/$model[prefix]pperp.data";
        runBasePhosPerp($infile, $outfile);
        $pperp = loadBasePhosPerp($outfile);
    }
    //}}} Run nucleic acid geometry programs and offer kins to user
    
    //{{{ Run all-atom contact programs and offer kins to user
    // Clashes
    if($runClashlist)
    {
        setProgress($tasks, 'clashlist'); // updates the progress display if running as a background job
        $outfile = "$chartDir/$model[prefix]clashlist.txt";
        runClashlist($infile, $outfile);
        $clash = loadClashlist($outfile);
        //$clashPct = runClashStats($model['stats']['resolution'], $clash['scoreAll']);
        $tasks['clashlist'] .= " - <a href='viewtext.php?$_SESSION[sessTag]&file=$outfile&mode=plain' target='_blank'>preview</a>\n";
        setProgress($tasks, 'clashlist'); // so the preview link is visible
    }
    //}}} Run all-atom contact programs and offer kins to user
    
    //{{{ Build multi-criterion chart, kinemage
    if($opts['doMultiChart'])
    {
        setProgress($tasks, 'multichart'); // updates the progress display if running as a background job
        $outfile = "$chartDir/$model[prefix]multi.chart";
        makeMulticritChart($infile, $outfile, $clash, $rama, $rota, $cbdev, $pperp, $opts['multiChartSort']);
        $tasks['multichart'] .= " - <a href='viewtext.php?$_SESSION[sessTag]&file=$outfile&mode=html' target='_blank'>preview</a>\n";
        setProgress($tasks, 'multichart'); // so the preview link is visible
    }
    if($opts['doMultiKin'])
    {
        setProgress($tasks, 'multikin'); // updates the progress display if running as a background job
        $mcKinOpts = array(
            'ribbons'   =>  $opts['multiKinExtras'],
            'Bscale'    =>  $opts['multiKinExtras'],
            'Qscale'    =>  $opts['multiKinExtras'],
            'altconf'   =>  $opts['multiKinExtras'],
            'rama'      =>  isset($rama),
            'rota'      =>  isset($rota),
            'cbdev'     =>  isset($cbdev),
            'pperp'     =>  $opts['doBaseP'],
            'dots'      =>  $opts['doAAC'],
            'hbdots'    =>  $opts['showHbonds'],
            'vdwdots'   =>  $opts['showContacts']
        );
        $outfile = "$kinDir/$model[prefix]multi.kin";
        makeMulticritKin(array($infile), $outfile, $mcKinOpts);
    }
    //}}} Build multi-criterion chart, kinemage
    
    //{{{ Create lab notebook entry
    $entry = "";
    if($opts['doSummaryStats'])
    {
        $entry .= "<h3>Summary statistics</h3>\n";
        $entry .= makeSummaryStatsTable($model['stats']['resolution'], $clash, $rama, $rota, $cbdev, $pperp);
    }
    if($opts['doMultiKin'] || $opts['doMultiChart'])
    {
        $entry .= "<h3>Multi-criterion visualizations</h3>\n";
        if($opts['doMultiKin'])
            $entry .= "<p>".linkKinemage("$model[prefix]multi.kin", "Multi-criterion kinemage")."</p>\n";
        if($opts['doMultiChart'])
            $entry .= "<p><a href='viewtext.php?$_SESSION[sessTag]&file=$chartDir/$model[prefix]multi.chart&mode=html' target='_blank'>Multi-criterion chart</a></p>\n";
    }
    
    $entry .= "<h3>Single-criterion visualizations</h3>";
    $entry .= "<ul>\n";
    if($runClashlist)
        $entry .= "<li><a href='viewtext.php?$_SESSION[sessTag]&file=$chartDir/$model[prefix]clashlist.txt&mode=plain' target='_blank'>Clash list</a></li>\n";
    if($opts['doRama'])
    {
        $entry .= "<li>".linkKinemage("$model[prefix]rama.kin", "Ramachandran plot kinemage")."</li>\n";
        $entry .= "<li><a href='$chartURL/$model[prefix]rama.pdf' target='_blank'>Ramachandran plot PDF</a></li>\n";
    }
    if($opts['doCbDev'])
    {
        $entry .= "<li>".linkKinemage("$model[prefix]cb2d.kin", "C&beta; deviation scatter plot (2D)")."</li>\n";
    }
    $entry .= "</ul>\n";
    //}}} Create lab notebook entry
    
    setProgress($tasks, null); // everything is finished
    return $entry;
}
#}}}########################################################################

#{{{ makeSummaryStatsTable - HTML table summary of validation statistics
############################################################################
/**
* Documentation for this function.
*/
function makeSummaryStatsTable($resolution, $clash, $rama, $rota, $cbdev, $pperp)
{
    $entry = "";
    $bgPoor = '#ff9999';
    $bgFair = '#ffff99';
    $bgGood = '#99ff99';
    
    $entry .= "<p><table border='1' width='100%'>\n";
    if(is_array($clash))
    {
        $clashPct = runClashStats($resolution, $clash['scoreAll']);
        if($clashPct['pct_rank'] <= 33)     $bg = $bgPoor;
        elseif($clashPct['pct_rank'] <= 66) $bg = $bgFair;
        else                                $bg = $bgGood;
        $entry .= "<tr><td rowspan='2' align='center'>All-Atom<br>Contacts</td>\n";
        $entry .= "<td>Clashscore, all atoms:</td><td bgcolor='$bg'>$clash[scoreAll]</td>\n";
        $entry .= "<td>$clashPct[pct_rank]<sup>".ordinalSuffix($clashPct['pct_rank'])."</sup> percentile<sup>*</sup> (N=$clashPct[n_samples])</td></tr>\n";
        
        if($clashPct['pct_rank40'] <= 33)       $bg = $bgPoor;
        elseif($clashPct['pct_rank40'] <= 66)   $bg = $bgFair;
        else                                    $bg = $bgGood;
        $entry .= "<tr><td>Clashscore, B&lt;40:</td><td bgcolor='$bg'>$clash[scoreBlt40]</td>\n";
        $entry .= "<td>$clashPct[pct_rank40]<sup>".ordinalSuffix($clashPct['pct_rank40'])."</sup> percentile<sup>*</sup> (N=$clashPct[n_samples])</td></tr>\n";
    }
    $proteinRows = 0;
    if(is_array($rama))    $proteinRows += 2;
    if(is_array($rota))    $proteinRows += 1;
    if(is_array($cbdev))   $proteinRows += 1;
    if($proteinRows > 0)
    {
        $entry .= "<tr><td rowspan='$proteinRows' align='center'>Protein<br>Geometry</td>\n";
        $firstRow = true;
        if(is_array($rama))
        {
            $ramaOut = count(findRamaOutliers($rama));
            foreach($rama as $r) { if($r['eval'] == "Favored") $ramaFav++; }
            $ramaTot = count($rama);
            $ramaOutPct = sprintf("%.2f", 100.0 * $ramaOut / $ramaTot);
            $ramaFavPct = sprintf("%.2f", 100.0 * $ramaFav / $ramaTot);
            
            if($firstRow) $firstRow = false;
            else $entry .= "<tr>";

            if($ramaOut == 0) $bg = $bgGood;
            elseif($ramaOut == 1 || $ramaOutPct+0 <= 0.5) $bg = $bgFair;
            else $bg = $bgPoor;
            $entry .= "<td>Ramachandran outliers</td><td bgcolor='$bg'>$ramaOutPct%</td>\n";
            $entry .= "<td>Goal: &lt;0.05%</td></tr>\n";
            if($ramaFavPct+0 >= 98)     $bg = $bgGood;
            elseif($ramaFavPct+0 >= 95) $bg = $bgFair;
            else                        $bg = $bgPoor;
            $entry .= "<tr><td>Ramachandran favored</td><td bgcolor='$bg'>$ramaFavPct%</td>\n";
            $entry .= "<td>Goal: &gt;98%</td></tr>\n";
        }
        if(is_array($rota))
        {
            $rotaOut = count(findRotaOutliers($rota));
            $rotaTot = count($rota);
            $rotaOutPct = sprintf("%.2f", 100.0 * $rotaOut / $rotaTot);
            
            if($firstRow) $firstRow = false;
            else $entry .= "<tr>";
            
            if($rotaOutPct+0 <= 1)      $bg = $bgGood;
            elseif($rotaOutPct+0 <= 5)  $bg = $bgFair;
            else                        $bg = $bgPoor;
            $entry .= "<td>Rotamer outliers</td><td bgcolor='$bg'>$rotaOutPct%</td>\n";
            $entry .= "<td>Goal: &lt;1%</td></tr>\n";
        }
        if(is_array($cbdev))
        {
            $cbOut = count(findCbetaOutliers($cbdev));
            if($firstRow) $firstRow = false;
            else $entry .= "<tr>";
            
            if($cbOut == 0) $bg = $bgGood;
            else            $bg = $bgFair;
            $entry .= "<td>C&beta; deviations &gt;0.25&Aring;</td><td bgcolor='$bg'>$cbOut</td>\n";
            $entry .= "<td>Goal: 0</td></tr>\n";
        }
    }// end of protein-specific stats
    if(is_array($pperp))
    {
        $pperpOut = count(findBasePhosPerpOutliers($pperp));
        $pperpTot = count($pperp);
        if($pperpOut == 0)  $bg = $bgGood;
        else                $bg = $bgFair;
        $entry .= "<tr><td rowspan='1' align='center'>Nucleic Acid<br>Geometry</td>\n";
        $entry .= "<td>Base-P dist./pucker disagreement:</td><td bgcolor='$bg'>$pperpOut</td>\n";
        $entry .= "<td>Goal: 0</td></tr>\n";
        $entry .= "</tr>\n";
    }
    $entry .= "</table>\n";
    if(is_array($clash)) $entry .= "<small>* 0<sup>th</sup> percentile is the worst among structures between $clashPct[minresol]&Aring; and $clashPct[maxresol]&Aring;; 100<sup>th</sup> percentile is the best.</small>\n";
    $entry .= "</p>\n"; // end of summary stats table
    return $entry;
}
#}}}########################################################################

#{{{ runBasePhosPerp - generate tab file of base-phos perp distances
############################################################################
function runBasePhosPerp($infile, $outfile)
{
    exec("prekin -pperpdump $infile > $outfile");
}
#}}}########################################################################

#{{{ loadBasePhosPerp - load base-phos perp data into an array
############################################################################
/**
* Returns an array of entries, one per residue. Their keys:
*   resName         a formatted name for the residue: 'cnnnnittt'
*                       c: Chain ID, space for none
*                       n: sequence number, right justified, space padded
*                       i: insertion code, space for none
*                       t: residue type (ALA, LYS, etc.), all caps,
*                          left justified, space padded
*   resType         3-letter residue code (e.g. ALA)
*   chainID         1-letter chain ID or ' '
*   resNum          residue number
*   insCode         insertion code or ' '
*   5Pdist          distance from the base to the 5' phosphate (?)
*   3Pdist          distance from the base to the 3' phosphate (?)
*   delta           delta angle of the sugar ring
*   outlier         true if the sugar pucker (delta) doesn't match P dist
*/
function loadBasePhosPerp($datafile)
{
    $data = file($datafile);
    foreach($data as $line)
    {
        $line = trim($line);
        if($line != "" && !startsWith($line, ':pdb:res:'))
        {
            $line = explode(':', $line);
            $entry = array(
                'resType'   => strtoupper(substr($line[2],1,-1)),
                'chainID'   => strtoupper(substr($line[3],1,-1)),
                'resNum'    => trim(substr($line[4], 0, -1)) + 0,
                'insCode'   => substr($line[4], -1),
                '5Pdist'    => $line[5] + 0,
                '3Pdist'    => $line[6] + 0,
                'delta'     => $line[7] + 0,
                'outlier'   => (trim($line[8]) ? true : false)
            );
            $entry['resName']   = $entry['chainID']
                                . str_pad($entry['resNum'], 4, ' ', STR_PAD_LEFT)
                                . $entry['insCode']
                                . str_pad($entry['resType'], 3, ' ', STR_PAD_RIGHT);
            $ret[] = $entry;
        }
    }
    return $ret;
}
#}}}########################################################################

#{{{ findBasePhosPerpOutliers - evaluates residues for bad score
############################################################################
/**
* Returns an array of 9-char residue names for residues that
* fall outside the allowed boundaries for this criteria.
* Inputs are from appropriate loadXXX() function above.
*/
function findBasePhosPerpOutliers($input)
{
    $worst = array();
    if(is_array($input)) foreach($input as $res)
    {
        if($res['outlier'])
            $worst[$res['resName']] = $res['resName'];
    }
    ksort($worst); // Put the residues into a sensible order
    return $worst;
}
#}}}########################################################################

#{{{ runCbetaDev - generates numeric info about CB deviations
############################################################################
function runCbetaDev($infile, $outfile)
{
    exec("prekin -cbdevdump $infile > $outfile");
}
#}}}########################################################################

#{{{ loadCbetaDev - loads Prekin cbdevdump output into an array
############################################################################
/**
* Returns an array of entries, one per residue. Their keys:
*   altConf         alternate conformer flag, or ' ' for none
*   resName         a formatted name for the residue: 'cnnnnittt'
*                       c: Chain ID, space for none
*                       n: sequence number, right justified, space padded
*                       i: insertion code, space for none
*                       t: residue type (ALA, LYS, etc.), all caps,
*                          left justified, space padded
*   resType         3-letter residue code (e.g. ALA)
*   chainID         1-letter chain ID or ' '
*   resNum          residue number
*   insCode         insertion code or ' '
*   dev             deviation distance, in Angstroms
*   dihedral        N-CA-idealCB-actualCB angle, in degrees
*   occ             occupancy, between 0 and 1
*/
function loadCbetaDev($datafile)
{
    $data = file($datafile);
    foreach($data as $line)
    {
        $line = trim($line);
        if($line != "" && !startsWith($line, 'pdb:alt:res:'))
        {
            $line = explode(':', $line);
            $entry = array(
                'altConf'   => strtoupper($line[1]),
                'resType'   => strtoupper($line[2]),
                'chainID'   => strtoupper($line[3]),
                'resNum'    => trim(substr($line[4], 0, -1)) + 0,
                'insCode'   => substr($line[4], -1),
                'dev'       => $line[5] + 0,
                'dihedral'  => $line[6] + 0,
                'occ'       => $line[7] + 0
            );
            $entry['resName']   = $entry['chainID']
                                . str_pad($entry['resNum'], 4, ' ', STR_PAD_LEFT)
                                . $entry['insCode']
                                . str_pad($entry['resType'], 3, ' ', STR_PAD_RIGHT);
            $ret[] = $entry;
        }
    }
    return $ret;
}
#}}}########################################################################

#{{{ findCbetaOutliers - evaluates residues for bad score
############################################################################
/**
* Returns an array of 9-char residue names for residues that
* fall outside the allowed boundaries for this criteria.
* Inputs are from appropriate loadXXX() function above.
*/
function findCbetaOutliers($cbdev)
{
    $worst = array();
    if(is_array($cbdev)) foreach($cbdev as $res)
    {
        if($res['dev'] >= 0.25)
            $worst[$res['resName']] = $res['dev'];
    }
    ksort($worst); // Put the residues into a sensible order
    return $worst;
}
#}}}########################################################################

#{{{ calcCbetaStats - calculates max, mean, and median deviation
############################################################################
/**
* Accepts the data structure created by loadCbetaDev()
* Returns an array with the keys 'max', 'mean', 'median'
*/
function calcCbetaStats($cbdev)
{
    if(!(is_array($cbdev) && count($cbdev) >= 2)) return array();
    
    foreach($cbdev as $cb)
    {
        $dev[] = $cb['dev'];
        $sum += $cb['dev'];
    }
    sort($dev);
    
    $len = count($dev);
    $s['max']   = $dev[ $len-1 ];
    $s['mean']  = $mean = $sum / $len;
    
    $half = intval($len/2);
    if($len % 2 == 0)   $s['median'] = ($dev[$half] + $dev[$half-1]) / 2;
    else                $s['median'] = $dev[$half];
    
    return $s;
}
#}}}########################################################################

#{{{ runClashlist - generates clash data with Clashlist
############################################################################
function runClashlist($infile, $outfile)
{
    exec("clashlist $infile > $outfile");
}
#}}}########################################################################

#{{{ loadClashlist - loads Clashlist output into an array
############################################################################
/**
* Returns an array with the following keys:
*   scoreAll        the overall clashscore
*   scoreBlt40      the score for atoms with B < 40
*   clashes         an array with 'cnnnnittt' residue names as keys
*                   (see loadCbetaDev() for explanation of naming)
*                   and maximum clashes as values (positive Angstroms).
*                   NB: only clashes >= 0.40A are currently listed.
*/
function loadClashlist($datafile)
{
    $data = file($datafile);
    $sum = array_values(array_slice($data, -2)); // last 2 lines with new indexes
    $scores = explode(':', $sum[0]);
    if(startsWith($sum[0], "#sum2")) // in case no clashes, thus no summary
    {
        $ret['scoreAll']    = $scores[2] + 0;
        $ret['scoreBlt40']  = $scores[3] + 0;
    }
    
    // Parse data about individual clashes
    $clashes = array(); // in case there are no clashes
    foreach($data as $datum)
    {
        // Ignore blank lines and #sum... lines
        // That leaves lines starting with colons.
        if($datum{0} == ':')
        {
            $line = explode(':', $datum);
            $res1 = substr($line[2], 0, 9);
            $res2 = substr($line[3], 0, 9);
            $dist = abs(trim($line[4])+0);
            if(!isset($clashes[$res1]) || $clashes[$res1] < $dist)
                $clashes[$res1] = $dist;
            if(!isset($clashes[$res2]) || $clashes[$res2] < $dist)
                $clashes[$res2] = $dist;
        }
    }
    $ret['clashes'] = $clashes;
    
    return $ret;
}
#}}}########################################################################

#{{{ findClashOutliers - evaluates residues for bad score
############################################################################
/**
* Returns an array of 9-char residue names for residues that
* fall outside the allowed boundaries for this criteria.
* Inputs are from appropriate loadXXX() function above.
*/
function findClashOutliers($clash)
{
    $worst = array();
    if(is_array($clash)) foreach($clash['clashes'] as $res => $dist)
    {
        if($dist >= 0.4)
            $worst[$res] = $dist;
    }
    ksort($worst); // Put the residues into a sensible order
    return $worst;
}
#}}}########################################################################

#{{{ runClashStats - percentile ranks a clashscore vs a "reprsentative" database
############################################################################
/**
* Returns an array with the following fields:
*   minresol        minimum resolution considered comparable, or 0 for OOB
*   maxresol        maximum resolution considered comparable, or 9999 for OOB
*   n_samples       number of structures in the comparison group (i.e., "N")
*   pct_rank        the percentile rank of this structure, 100 is great, 0 is bad
*   pct_rank40      the percentile rank of this structure for B < 40
*/
function runClashStats($resol, $clashscore)
{
    // Determine the percentile rank of this structure
    // fields are minres, maxres, n_samples, pct_rank
    $bin = MP_BASE_DIR.'/bin';
    $lib = MP_BASE_DIR.'/lib';
    $cmd = "gawk -v res=$resol -v cs=$clashscore -f $bin/cs-rank.awk $lib/clashscore.db.tab";
    //echo $cmd."\n";
    $fields = explode(":", trim(shell_exec($cmd)));
    //print_r($fields);
    return array(
        'minresol'      => $fields[0],
        'maxresol'      => $fields[1],
        'n_samples'     => $fields[2],
        'pct_rank'      => $fields[3],
        'pct_rank40'    => $fields[4]
    );
}
#}}}########################################################################

#{{{ runRotamer - generates rotamer analysis data
############################################################################
function runRotamer($infile, $outfile)
{
    exec("java -cp ".MP_BASE_DIR."/lib/hless.jar hless.Rotamer -raw $infile > $outfile");
}
#}}}########################################################################

#{{{ loadRotamer - loads Rotamer output into an array
############################################################################
/**
* Returns an array of entries, one per residue. Their keys:
*   resName         a formatted name for the residue: 'cnnnnittt'
*                       c: Chain ID, space for none
*                       n: sequence number, right justified, space padded
*                       i: insertion code, space for none
*                       t: residue type (ALA, LYS, etc.), all caps,
*                          left justified, space padded
*   resType         3-letter residue code (e.g. ALA)
*   chainID         1-letter chain ID or ' '
*   resNum          residue number
*   insCode         insertion code or ' '
*   scorePct        the percentage score from 0 (bad) to 100 (good)
*   chi1            the chi-1 angle
*   chi2            the chi-2 angle ("" for none)
*   chi3            the chi-3 angle ("" for none)
*   chi4            the chi-4 angle ("" for none)
*/
function loadRotamer($datafile)
{
    $data = array_slice(file($datafile), 1); // drop first line
    $ret = array();
    foreach($data as $line)
    {
        $line = explode(':', rtrim($line));
        $decomp = decomposeResName($line[0]);
        $ret[] = array(
            'resName'   => $line[0],
            'resType'   => $decomp['resType'],
            'chainID'   => $decomp['chainID'],
            'resNum'    => $decomp['resNum'],
            'insCode'   => $decomp['insCode'],
            'scorePct'  => $line[1] + 0,
            'chi1'      => $line[2] + 0,
            'chi2'      => $line[3] + 0,
            'chi3'      => $line[4] + 0,
            'chi4'      => $line[5] + 0
        );
    }
    return $ret;
}
#}}}########################################################################

#{{{ findRotaOutliers - evaluates residues for bad score
############################################################################
/**
* Returns an array of 9-char residue names for residues that
* fall outside the allowed boundaries for this criteria.
* Inputs are from appropriate loadXXX() function above.
*/
function findRotaOutliers($rota)
{
    $worst = array();
    if(is_array($rota)) foreach($rota as $res)
    {
        if($res['scorePct'] <= 1.0)
            $worst[$res['resName']] = $res['scorePct'];
    }
    ksort($worst); // Put the residues into a sensible order
    return $worst;
}
#}}}########################################################################

#{{{ runRamachandran - generates rotamer analysis data
############################################################################
function runRamachandran($infile, $outfile)
{
    exec("java -cp ".MP_BASE_DIR."/lib/hless.jar hless.Ramachandran -nokin -raw $infile > $outfile");
}
#}}}########################################################################

#{{{ loadRamachandran - loads Ramachandran output into an array
############################################################################
/**
* Returns an array of entries, one per residue. Their keys:
*   resName         a formatted name for the residue: 'cnnnnittt'
*                       c: Chain ID, space for none
*                       n: sequence number, right justified, space padded
*                       i: insertion code, space for none
*                       t: residue type (ALA, LYS, etc.), all caps,
*                          left justified, space padded
*   resType         3-letter residue code (e.g. ALA)
*   chainID         1-letter chain ID or ' '
*   resNum          residue number
*   insCode         insertion code or ' '
*   scorePct        the percentage score from 0 (bad) to 100 (good)
*   phi             the phi angle
*   psi             the psi angle
*   eval            "Favored", "Allowed", or "OUTLIER"
*   type            "General case", "Glycine", "Proline", or "Pre-proline"
*/
function loadRamachandran($datafile)
{
    $data = array_slice(file($datafile), 1); // drop first line
    $ret = array();
    foreach($data as $line)
    {
        $line = explode(':', rtrim($line));
        $decomp = decomposeResName($line[0]);
        $ret[] = array(
            'resName'   => $line[0],
            'resType'   => $decomp['resType'],
            'chainID'   => $decomp['chainID'],
            'resNum'    => $decomp['resNum'],
            'insCode'   => $decomp['insCode'],
            'scorePct'  => $line[1] + 0,
            'phi'       => $line[2] + 0,
            'psi'       => $line[3] + 0,
            'eval'      => $line[4],
            'type'      => $line[5]
        );
    }
    return $ret;
}
#}}}########################################################################

#{{{ findRamaOutliers - evaluates residues for bad score
############################################################################
/**
* Returns an array of 9-char residue names for residues that
* fall outside the allowed boundaries for this criteria.
* Inputs are from appropriate loadXXX() function above.
*/
function findRamaOutliers($rama)
{
    $worst = array();
    if(is_array($rama)) foreach($rama as $res)
    {
        if($res['eval'] == 'OUTLIER')
            $worst[$res['resName']] = $res['eval'];
    }
    ksort($worst); // Put the residues into a sensible order
    return $worst;
}
#}}}########################################################################

#{{{ decomposeResName - breaks a 9-character packed name into pieces
############################################################################
/**
* Decomposes this:
*   resName         a formatted name for the residue: 'cnnnnittt'
*                       c: Chain ID, space for none
*                       n: sequence number, right justified, space padded
*                       i: insertion code, space for none
*                       t: residue type (ALA, LYS, etc.), all caps,
*                          left justified, space padded
*
* Into this (as an array):
*   resType         3-letter residue code (e.g. ALA)
*   chainID         1-letter chain ID or ' '
*   resNum          residue number
*   insCode         insertion code or ' '
*/
function decomposeResName($name)
{
    return array(
        'resType'   => substr($name, 6, 3),
        'chainID'   => substr($name, 0, 1),
        'resNum'    => trim(substr($name, 1, 4))+0,
        'insCode'   => substr($name, 5, 1)
    );
}
#}}}########################################################################

#{{{ pdbComposeResName - makes a 9-char res ID from a PDB ATOM line
############################################################################
function pdbComposeResName($pdbline)
{
    return substr($pdbline, 21, 6) . substr($pdbline, 17, 3);
}
#}}}########################################################################

#{{{ findAltConfs - parses a PDB file for residues with mc and/or sc alts
############################################################################
/**
* Returns NULL if the file could not be read.
* Otherwise, returns an array of arrays of booleans.
* First key is 'mc', 'sc', or 'all';
* second key is the 9-char residue ID.
*/
function findAltConfs($infile)
{
    $mcAtoms = array(" N  " => true, " CA " => true, " C  " => true, " O  " => true,
        " H  " => true, " HA " => true, "1HA " => true, "2HA " => true);
    
    $out = array('all' => array(), 'mc' => array(), 'sc' => array());
    $in = fopen($infile, "r");
    if(!$in) return NULL;
    while(!feof($in))
    {
        $s = fgets($in, 1024);
        $alt = $s{16};
        if($alt != ' ' && (startsWith($s, "ATOM") || startsWith($s, "HETATM")))
        {
            $res    = pdbComposeResName($s);
            $atom   = substr($s, 12, 4);
            $out['all'][$res] = true;
            
            if($mcAtoms[$atom])
                $out['mc'][$res] = true;
            else
                $out['sc'][$res] = true;
        }
    }
    fclose($in);
    
    return $out;
}
#}}}########################################################################

#{{{ listResidues - lists CNIT codes for all residues in a PDB file
############################################################################
/**
* Returns NULL if the file could not be read.
* Otherwise, an array of CNIT 9-char residue codes (in keys and values).
* Does not account for the possibility of multiple MODELs
*/
function listResidues($infile)
{
    $out = array();
    
    $in = fopen($infile, "r");
    if(!$in) return NULL;
    while(!feof($in))
    {
        $s = fgets($in, 1024);
        if(startsWith($s, "ATOM") || startsWith($s, "HETATM"))
        {
            $res = pdbComposeResName($s);
            $out[$res] = $res;
        }
    }
    fclose($in);

    return $out;
}
#}}}########################################################################

#{{{ listProteinResidues - lists CNIT codes for amino acid residues in a PDB
############################################################################
/**
* Returns NULL if the file could not be read.
* Otherwise, an array of CNIT 9-char residue codes.
* Does not account for the possibility of multiple MODELs
*/
function listProteinResidues($infile)
{
    $protein = array('GLY'=>1, 'ALA'=>1, 'VAL'=>1, 'LEU'=>1, 'ILE'=>1, 'PRO'=>1,
        'PHE'=>1, 'TYR'=>1, 'TRP'=>1, 'SER'=>1, 'THR'=>1, 'CYS'=>1, 'MET'=>1,
        'MSE'=>1, 'LYS'=>1, 'HIS'=>1, 'ARG'=>1, 'ASP'=>1, 'ASN'=>1, 'GLN'=>1,
        'GLU'=>1);
    $out = array();
    
    $in = fopen($infile, "r");
    if(!$in) return NULL;
    while(!feof($in))
    {
        $s = fgets($in, 1024);
        if(startsWith($s, "ATOM") || startsWith($s, "HETATM"))
        {
            $res = pdbComposeResName($s);
            if(isset($protein[substr($res,6,3)]))
                $out[$res] = $res;
        }
    }
    fclose($in);

    return $out;
}
#}}}########################################################################

#{{{ computeResCenters - finds (x,y,z) for residue (pseudo) center-of-mass from PDB
############################################################################
/**
* Returns NULL if the file could not be read.
* Otherwise, an array of arrays
* where the first key is the 9-char residue code
* and the second key is 'x', 'y', or 'z'.
* Does not account for the possibility of multiple MODELs
*/
function computeResCenters($infile)
{
    $out = array(); // x, y, z
    $cnt = array(); // how many atoms have been tallied
    
    $in = fopen($infile, "r");
    if(!$in) return NULL;
    while(!feof($in))
    {
        $s = fgets($in, 1024);
        if(startsWith($s, "ATOM") || startsWith($s, "HETATM"))
        {
            $res = pdbComposeResName($s);
            $out[$res]['x'] += substr($s, 30, 8) + 0.0;
            $out[$res]['y'] += substr($s, 38, 8) + 0.0;
            $out[$res]['z'] += substr($s, 46, 8) + 0.0;
            $cnt[$res]      += 1;
        }
    }
    fclose($in);
    
    foreach($cnt as $res => $num)
    {
        $out[$res]['x'] /= $num;
        $out[$res]['y'] /= $num;
        $out[$res]['z'] /= $num;
    }
    
    return $out;
}
#}}}########################################################################

#{{{ groupAdjacentRes - structures a list of residues into chains and "runs"
############################################################################
/**
* Given a list of 9-char residue codes as the values (not keys) of an array,
* a new data structure is created where
* the first index is a one-char chain ID,
* the second index is an arbitrary run number,
* the third index is arbitrary, and
* the value is the 9-char residue code.
* The so-called "runs" are just residues that were adjacent in the input list
* and had sequence numbers that differed by 1 (or 0).
*/
function groupAdjacentRes($resList)
{
    $out = array();
    if(is_array($resList)) foreach($resList as $res)
    {
        $num = substr($res, 1, 4) + 0;
        // If old run is ending, append it and start fresh:
        if(isset($run) && !($num - $prevNum <= 1 && $chainID == $res{0}))
        {
            $out[$chainID][] = $run;
            unset($run);
        }
        // Append this residue to the current run (which is potentially empty)
        $prevNum    = $num;
        $chainID    = $res{0};
        $run[]      = $res;
    }
    
    // Append the last run, if any
    if(isset($run)) $out[$chainID][] = $run;
    
    return $out;
}
#}}}########################################################################

#{{{ a_function_definition - sumary_statement_goes_here
############################################################################
/**
* Documentation for this function.
*/
//function someFunctionName() {}
#}}}########################################################################

#{{{ a_function_definition - sumary_statement_goes_here
############################################################################
/**
* Documentation for this function.
*/
//function someFunctionName() {}
#}}}########################################################################
?>
