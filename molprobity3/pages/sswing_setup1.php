<?php # (jEdit options) :folding=explicit:collapseFolds=1:
/*****************************************************************************
    This page allows the user to run SSWING.
    This page is for choosing the model and map; next is for choosing the res.
    It should be accessed by pageCall()
*****************************************************************************/
// This variable must be defined for index.php to work! Must match class below.
$delegate = new SSwingSetup1Delegate();
// We use a uniquely named wrapper class to avoid re-defining display(), etc.
class SSwingSetup1Delegate extends BasicDelegate {
    
#{{{ display - creates the UI for this page
############################################################################
/**
* Context may contain the following keys:
*   modelID     the model ID to add H to
*   map         the ED map to use
*/
function display($context)
{
    echo mpPageHeader("Refit sidechains");
    
    // Script to discourage people from choosing models without H
?><script language='JavaScript'>
<!--
function warnNoH(fileName)
{
    window.alert("The file you choose, "+fileName+", does not appear to have "+
        "all its H atoms added. SSwing requires all H atoms to function properly.")
}
// -->
</script><?php

    // Make sure we have some models with H added.
    if(count($_SESSION['models']) > 0 && count($_SESSION['edmaps']) > 0)
    {
        echo makeEventForm("onChooseResidues");
        echo "<h3>Select a model to work with:</h3>";
        echo "<p><table width='100%' border='0' cellspacing='0' cellpadding='2'>\n";
        $c = MP_TABLE_ALT1;
        foreach($_SESSION['models'] as $id => $model)
        {
            // Alternate row colors:
            $c == MP_TABLE_ALT1 ? $c = MP_TABLE_ALT2 : $c = MP_TABLE_ALT1;
            echo " <tr bgcolor='$c'>\n";
            if($model['isReduced'])
            {
                $checked = ($context['modelID'] == $id ? "checked" : "");
                echo "  <td><input type='radio' name='modelID' value='$id' $checked></td>\n";
                echo "  <td><b>$model[pdb]</b></td>\n";
                echo "  <td><small>$model[history]</small></td>\n";
            }
            else
            {
                $checked = ($context['modelID'] == $id ? "checked" : "");
                echo "  <td><input type='radio' name='modelID' value='$id' onclick='warnNoH(\"$model[pdb]\")' $checked></td>\n";
                echo "  <td><span class='inactive' title='Doesn&apos;t have H added'><b>$model[pdb]</b></span></td>\n";
                echo "  <td><span class='inactive'><small>$model[history]</small></span></td>\n";
            }
            echo " </tr>\n";
        }
        echo "</table></p>\n";

        echo "<h3>Select a CCP4-format electron density map:</h3>";
        echo "<p><table width='100%' border='0' cellspacing='0' cellpadding='2'>\n";
        $c = MP_TABLE_ALT1;
        foreach($_SESSION['edmaps'] as $map)
        {
            // Alternate row colors:
            $c == MP_TABLE_ALT1 ? $c = MP_TABLE_ALT2 : $c = MP_TABLE_ALT1;
            $checked = ($context['map'] == $map ? "checked" : "");
            echo " <tr bgcolor='$c'>\n";
            echo "  <td><input type='radio' name='map' value='$map' $checked>\n";
            echo "  <b>$map</b></td>\n";
            echo " </tr>\n";
        }
        echo "</table></p>\n";

        echo "<p><table width='100%' border='0'><tr>\n";
        echo "<td><input type='submit' name='cmd' value='Choose residues to refit &gt;'></td>\n";
        echo "<td align='right'><input type='submit' name='cmd' value='Cancel'></td>\n";
        echo "</tr></table></p></form>\n";
    }
    elseif(count($_SESSION['models']) == 0)
    {
        echo "No models are available. Please <a href='".makeEventURL("onNavBarCall", "upload_pdb_setup.php")."'>upload or fetch a PDB file</a> in order to continue.\n";
        echo makeEventForm("onReturn");
        echo "<p><input type='submit' name='cmd' value='Cancel'></p></form>\n";
        
    }
    elseif(!$modelCount)
    {
        echo "No models have H added. Please <a href='".makeEventURL("onNavBarCall", "reduce_setup.php")."'>add hydrogens to a PDB file</a> in order to continue.\n";
        echo makeEventForm("onReturn");
        echo "<p><input type='submit' name='cmd' value='Cancel'></p></form>\n";
        
    }
    else
    {
        echo "No electron density maps are available. Please <a href='".makeEventURL("onNavBarCall", "upload_other_setup.php")."'>upload a CCP4-format map</a> in order to continue.\n";
        echo makeEventForm("onReturn");
        echo "<p><input type='submit' name='cmd' value='Cancel'></p></form>\n";
    }
    
    echo mpPageFooter();
}
#}}}########################################################################

#{{{ onReturn
############################################################################
/**
* Documentation for this function.
*/
function onReturn($arg, $req)
{
    pageReturn();
}
#}}}########################################################################

#{{{ onChooseResidues
############################################################################
/**
* Documentation for this function.
*/
function onChooseResidues($arg, $req)
{
    if($req['cmd'] == 'Cancel')
    {
        pageReturn();
        return;
    }
    
    // Otherwise, moving forward:
    if(isset($req['modelID']) && isset($req['map']))
    {
        $ctx['modelID'] = $req['modelID'];
        $ctx['map']     = $req['map'];
        pageGoto("sswing_setup2.php", $ctx);
    }
    else
    {
        $ctx = getContext();
        if(isset($req['modelID']))  $ctx['modelID'] = $req['modelID'];
        if(isset($req['map']))      $ctx['map']     = $req['map'];
        setContext($ctx);
    }
}
#}}}########################################################################

#{{{ a_function_definition - sumary_statement_goes_here
############################################################################
/**
* Documentation for this function.
*/
//function someFunctionName() {}
#}}}########################################################################

}//end of class definition
?>
