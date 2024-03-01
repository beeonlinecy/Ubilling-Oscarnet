<?
if(cfr('PAYFIND')){
	function web_BackLink(){
		$result = wf_BackLink('?module=logistis2');
		return($result);
	}
	
	//Extracts categories from assignments table and replaces ID with category name
	function opcats($id){
		$query = "SELECT * FROM `logistis_categories` INNER JOIN (SELECT * FROM `logistis_cattypes`) AS ct ON (`logistis_categories`.`cat_id`=`ct`.`id`) WHERE `exp_id`=$id ";
		$opcats = simple_queryall($query);
		//$result = array();
	
		if(!empty($opcats)){
			foreach($opcats as $eachcat){
				$result = $eachcat['category'];
			}
		
		return($result);
		}
	}
	
	//Exctracts all operations with assigned category in SQL WHERE accessible format
    function OpSearchExctractByCatID($catid) {
        $catid = vf($catid, 3);
        $query = "SELECT `exp_id`,`cat_id` from `logistis_categories` WHERE `cat_id`='" . $catid . "';";
        $allcats = simple_queryall($query);
        $result = ' AND `id` IN (';

        if (!empty($allcats)) {
            foreach ($allcats as $io => $each) {
                $result .= "'" . $each['exp_id'] . "',";
            }
            $result = rtrim($result, ',');
        } else {
            $result .= "'" . zb_rand_string('12') . "'";
        }

        $result .= ') ';
        return ($result);
    }	
	
    /**
     * Returns available cashier accounts selector
     * 
     * @return string
     */
    function web_PayFindCashierSelector() {
        $alladmins = rcms_scandir(USERS_PATH);
        $adminlist = array();
        @$employeeLogins = unserialize(ts_GetAllEmployeeLoginsCached());
        $result = '';
        if (!empty($alladmins)) {
            foreach ($alladmins as $nu => $login) {
                $administratorName = (isset($employeeLogins[$login])) ? $employeeLogins[$login] : $login;
                $adminlist[$login] = $administratorName;
            }
            $adminlist['openpayz'] = __('OpenPayz');
            $result = wf_Selector('cashier', $adminlist, __('Cashier'), '', true, true);
        }
        return ($result);
    }
	
	/* Returns operation category dropdown */
	function web_CatSelector($current){
		$query = "SELECT `id`,`category` FROM `logistis_cattypes`";
		$result='';
		$cats = array();
		$allcats = simple_queryall($query);
		
		if(!empty($allcats)){
				$cats[0] = __('No category');
			foreach($allcats as $io => $eachcat){
				$cats[$eachcat['id']] = __($eachcat['category']);
			}
				
		}
		$result = wf_Selector('catid',$cats,__('Categories'),$current,false,false);
		return($result);
	}
	
	function web_OpSearch($markers,$joins = ''){
		$query = "SELECT * FROM `expenses`";
		$query.= $joins.$markers;
		
		$allops = simple_queryall($query);
		
		$totalsum = 0;
		$totalcount = 0;
		$profitsum = 0;
		
		$cells = wf_TableCell('ID');
		$cells.= wf_TableCell(__('Date'));
		$cells.= wf_TableCell(__('Cash'));
		$cells .= wf_TableCell(__('Category'));
		$cells .= wf_TableCell(__('Notes'));
		$cells .= wf_TableCell(__('Admin'));
		$rows = wf_TableRow($cells, 'row1');
		
		if(!empty($allops)){
				foreach($allops as $io => $each){
					$opcat = opcats($each['id']);
					
					$cells = wf_TableCell(wf_Link('?module=logistis_operation&opid='.$each['id'],$each['id']));
					$cells.= wf_TableCell($each['date']);
					$cells.= wf_TableCell($each['summ']);
					$cells.= wf_TableCell($opcat);
					$cells.= wf_TableCell($each['comment']);
					$cells.= wf_TableCell($each['user']);
					$rows.= wf_TableRow($cells, 'row3');
					
					$totalsum = $totalsum + $each['summ'];
				}
		}
		$result=wf_TableBody($rows,'100%','0','sortable');
		
		//Additional info
		$result .= wf_tag('div', false, 'glamour') . __('Count') . ': ' . $totalcount . wf_tag('div', true);
        $result .= wf_tag('div', false, 'glamour') . __('Total expenses') . ': ' . $totalsum . wf_tag('div', true);
        $result .= wf_CleanDiv();
		show_window('',$result);		
	}
	
	function web_SearchForm(){
		//Getting date info
		if(wf_CheckPost(array('datefrom','dateto'))){
			$curdate = $_POST['dateto'];
			$yesterday = $_POST['datefrom'];
		} else {
			$curdate = date("Y-m-d", time() + 60 * 60 * 24);
			$yesterday = curdate();
		}
		
		$inputs = __('Date');
		$inputs.=wf_DatePickerPreset('datefrom',$yesterday).' '.__('From');
		$inputs.=wf_DatePickerPreset('dateto',$curdate).' '.__('To');
		$inputs.=wf_delimiter();
        $inputs .= wf_CheckInput('type_sum', '', false, false);
        $inputs .= wf_TextInput('sum', __('Search by sum'), '', true, '10');
		
		$inputs .= wf_CheckInput('type_opnotecontains', '', false, false);
        $inputs .= wf_TextInput('opnotecontains', __('Notes contains'), '', true, '10');
		
		$inputs .= wf_CheckInput('type_cashier', '', false, false);
        $inputs .= web_PayFindCashierSelector();
		
		$inputs.=wf_CheckInput('type_category',false,false);
		$inputs.=web_CatSelector(0);
		$inputs.=wf_delimiter();
		
		$inputs.=wf_HiddenInput('dosearch','true');
		$inputs.=wf_Submit(__('Search'));
		
		$result = wf_Form('','POST',$inputs,'glamour');
		return($result);
	}
	
	show_window(__('Operation search'),web_SearchForm());
	show_window('',web_BackLink());
	
	//Search request
	$markers = '';
	$joins = '';
	

	//date search
    if (wf_CheckPost(array('datefrom', 'dateto'))) {
        $datefrom = mysql_real_escape_string($_POST['datefrom']);
        $dateto = mysql_real_escape_string($_POST['dateto']);
        $markers .= "WHERE `date` BETWEEN '" . $datefrom . "' AND '" . $dateto . "' ";
    }
	
	//Search by sum
	if(wf_CheckPost(array('type_sum','sum'))){
		$sum = mysql_real_escape_string($_POST['sum']);
		$markers.="AND `summ`=$sum ";
	}
	
    //payment notes contains search
    if (wf_CheckPost(array('type_opnotecontains', 'opnotecontains'))) {
        $notesMask = mysql_real_escape_string($_POST['opnotecontains']);
        $markers .= "AND `comment` LIKE '%" . $notesMask . "%' ";
    }	
	//cashiers search
    if (wf_CheckPost(array('type_cashier', 'cashier'))) {
        $cashierLogin = mysql_real_escape_string($_POST['cashier']);
        $markers .= "AND `user`='" . $cashierLogin . "' ";
    }
	//by category
	if(wf_CheckPost(array('type_category','catid'))){
		$cat_id = mysql_real_escape_string($_POST['catid']);
		$markers.= OpSearchExctractByCatID($cat_id);
	}
	
	if(wf_CheckPost(array('dosearch'))){
		web_OpSearch($markers,$joins);
	}
	
} else {
	show_error(__('Access denied'));
}
?>