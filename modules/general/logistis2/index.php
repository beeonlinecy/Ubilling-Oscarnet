<?php
//Version 2.0.1


//Access righs for user
if (cfr('LOGISTIS')){
		$alter = $ubillingConfig->getAlter();
		
		$total = 0;
		$total_exp = 0;
		
			/*** Functions ***/

/**
 * Renders expenses extracted from database with some query
 * 
 * @param string $query
 * @return string
 */
function web_ExpensesShow($query) {
    $alter_conf = rcms_parse_ini_file(CONFIG_PATH . 'alter.ini');
    $alladrs = zb_AddressGetFulladdresslistCached();
    $allrealnames = zb_UserGetAllRealnames();
    $alltypes = zb_CashGetAllCashTypes();
	
    $allexpenses = simple_queryall($query);
	
	
	//$exp_cats = exp_assignments();
	
    $allservicenames = zb_VservicesGetAllNamesLabeled();


    //getting all users tariffs
    if ($alter_conf['FINREP_TARIFF']) {
        $alltariffs = zb_TariffsGetAllUsers();
    }

    $total = 0;
    $totalPaycount = 0;

    $cells = wf_TableCell(__('ID'));
    $cells .= wf_TableCell(__('IDENC'));
    $cells .= wf_TableCell(__('Date'));
    $cells .= wf_TableCell(__('Cash'));

    $cells .= wf_TableCell(__('Full address'));
    $cells .= wf_TableCell(__('Company'));
    //optional tariff display
    if ($alter_conf['FINREP_TARIFF']) {
        $cells .= wf_TableCell(__('Tariff'));
    }
    $cells .= wf_TableCell(__('Category'));
    $cells .= wf_TableCell(__('Notes'));
    $cells .= wf_TableCell(__('Admin'));
    $rows = wf_TableRow($cells, 'row1');

    if (!empty($allexpenses)) {
        foreach ($allexpenses as $io => $eachexpense) {
			$opcat = opcats($eachexpense['id']);
			
            if ($alter_conf['TRANSLATE_PAYMENTS_NOTES']) {
                $eachexpense['comment'] = zb_TranslatePaymentNote($eachexpense['comment'], $allservicenames);
            }

            $cells = wf_TableCell(wf_Link("?module=logistis_operation&opid=".$eachexpense['id'],$eachexpense['id']));
            $cells .= wf_TableCell(zb_NumEncode($eachexpense['id']));
            $cells .= wf_TableCell($eachexpense['date']);
            $cells .= wf_TableCell($eachexpense['summ']);

            $cells .= wf_TableCell(@$alladrs[$eachexpense['login']]);
            $cells .= wf_TableCell(@get_CompanyNameByOpId($eachexpense['id']));
            //optional tariff display
            if ($alter_conf['FINREP_TARIFF']) {
                $cells .= wf_TableCell(@$alltariffs[$eachexpense['login']]);
            }
         
			$cells .= wf_TableCell(wf_Link("?module=logistis2&cat=",$opcat));
            $cells .= wf_TableCell($eachexpense['comment']);
            $cells .= wf_TableCell($eachexpense['user']);
            $rows .= wf_TableRow($cells, 'row3');

            if ($eachexpense['summ'] > 0) {
                $total = $total + $eachexpense['summ'];
                $totalPaycount++;
            }
        }
    }

    $result = wf_TableBody($rows, '100%', '0', 'sortable');
    $result .= wf_tag('strong') . __('Cash') . ': ' . $total . wf_tag('strong', true) . wf_tag('br');
    $result .= wf_tag('strong') . __('Count') . ': ' . $totalPaycount . wf_tag('strong', true);
    return($result);
}

/**
 * Returns year expenses sum
 * 
 * @param int $year
 * @return float
 */
function ExpensesGetYearSum($year){
	$year = vf($year);
	$query = "SELECT SUM(`summ`) from `expenses` WHERE `date` LIKE '" . $year . "-%' AND `summ` > 0";
    $result = simple_query($query);
    return($result['SUM(`summ`)']);
}

/**
 * Shows payments year graph with caching
 * 
 * @param int $year
 */
function web_ExpensesShowGraph($year) {
    global $ubillingConfig;
    $months = months_array();
    $year_summ = ExpensesGetYearSum($year);
    $curtime = time();
    $yearPayData = array();
    $yearStats = array();
    $cache = new UbillingCache();
    $cacheTime = 3600; //sec intervall to cache

    $cells = wf_TableCell('');
    $cells .= wf_TableCell(__('Month'));
    $cells .= wf_TableCell(__('Expense count'));
    //$cells .= wf_TableCell(__('ARPU'));
    $cells .= wf_TableCell(__('Cash'));
    $cells .= wf_TableCell(__('Visual'), '50%');
    $rows = wf_TableRow($cells, 'row1');

    //caching subroutine

    $renewTime = $cache->get('YLOG_LAST', $cacheTime);
    if (empty($renewTime)) {
        //first usage
        $renewTime = $curtime;
        $cache->set('YLOG_LAST', $renewTime, $cacheTime);
        $updateCache = true;
    } else {
        //cache time already set
        $timeShift = $curtime - $renewTime;
        if ($timeShift > $cacheTime) {
            //cache update needed
            $updateCache = true;
        } else {
            //load data from cache or init new cache
            $yearPayData_raw = $cache->get('YLOG_CACHE', $cacheTime);
            if (empty($yearPayData_raw)) {
                //first usage
                $emptyCache = array();
                $emptyCache = serialize($emptyCache);
                $emptyCache = base64_encode($emptyCache);
                $cache->set('YLOG_CACHE', $emptyCache, $cacheTime);
                $updateCache = true;
            } else {
                // data loaded from cache
                $yearPayData = base64_decode($yearPayData_raw);
                $yearPayData = unserialize($yearPayData);
                $updateCache = false;
                //check is current year already cached?
                if (!isset($yearPayData[$year]['graphs'])) {
                    $updateCache = true;
                }

                //check is manual cache refresh is needed?
                if (wf_CheckGet(array('forcecache'))) {
                    $updateCache = true;
                    rcms_redirect("?module=logistis2");
                }
            }
        }
    }

    if ($updateCache) {
        $dopWhere = '';
        if ($ubillingConfig->getAlterParam('REPORT_FINANCE_IGNORE_ID')) {
            $exIdArr = array_map('trim', explode(',', $ubillingConfig->getAlterParam('REPORT_FINANCE_IGNORE_ID')));
            $exIdArr = array_filter($exIdArr);
            // Create and WHERE to query
            if (!empty($exIdArr)) {
                $dopWhere = ' AND ';
                $dopWhere .= ' `cashtypeid` != ' . implode(' AND `cashtypeid` != ', $exIdArr);
            }
        }
        //extracting all of needed payments in one query
        if ($ubillingConfig->getAlterParam('REPORT_FINANCE_CONSIDER_NEGATIVE')) {
            // ugly way to get payments with negative sums
            // performance degradation is kinda twice
            $allYearPayments_q = "(SELECT * FROM `expenses` 
                                        WHERE `date` LIKE '" . $year . "-%' AND `summ` < '0' 
                                            AND note NOT LIKE 'Service:%' 
                                            AND note NOT LIKE 'PENALTY%' 
                                            AND note NOT LIKE 'OMEGATV%' 
                                            AND note NOT LIKE 'MEGOGO%' 
                                            AND note NOT LIKE 'TRINITYTV%' " . $dopWhere . ") 
                                  UNION ALL 
                                  (SELECT * FROM `expenses` WHERE `date` LIKE '" . $year . "-%' AND `summ` > '0' " . $dopWhere . ")";
        } else {
            $allYearPayments_q = "SELECT * FROM `expenses` WHERE `date` LIKE '" . $year . "-%' AND `summ` > '0' " . $dopWhere;
        }

        $allYearPayments = simple_queryall($allYearPayments_q);
        if (!empty($allYearPayments)) {
            foreach ($allYearPayments as $idx => $eachYearPayment) {
                //Here we can get up to 50% of CPU time on month extraction, but this hacks is to ugly :(
                //Benchmark results: http://pastebin.com/i7kadpN7
                $statsMonth = date("m", strtotime($eachYearPayment['date']));
                if (isset($yearStats[$statsMonth])) {
                    $yearStats[$statsMonth]['count'] ++;
                    $yearStats[$statsMonth]['summ'] = $yearStats[$statsMonth]['summ'] + $eachYearPayment['summ'];
                } else {
                    $yearStats[$statsMonth]['count'] = 1;
                    $yearStats[$statsMonth]['summ'] = $eachYearPayment['summ'];
                }
            }
        }

        foreach ($months as $eachmonth => $monthname) {
            $month_summ = (isset($yearStats[$eachmonth])) ? $yearStats[$eachmonth]['summ'] : 0;
            $paycount = (isset($yearStats[$eachmonth])) ? $yearStats[$eachmonth]['count'] : 0;
            $monthArpu = @round($month_summ / $paycount, 2);
            if (is_nan($monthArpu)) {
                $monthArpu = 0;
            }
            $cells = wf_TableCell($eachmonth);
            $cells .= wf_TableCell(wf_Link('?module=logistis2&month=' . $year . '-' . $eachmonth, rcms_date_localise($monthname)));
            $cells .= wf_TableCell($paycount);
            //$cells .= wf_TableCell($monthArpu);
            $cells .= wf_TableCell(zb_CashBigValueFormat($month_summ), '', '', 'align="right"');
            $cells .= wf_TableCell(web_bar($month_summ, $year_summ), '', '', 'sorttable_customkey="' . $month_summ . '"');
            $rows .= wf_TableRow($cells, 'row3');
        }
        $result = wf_TableBody($rows, '100%', '0', 'sortable');
        $yearPayData[$year]['graphs'] = $result;
        //write to cache
        $cache->set('YLOG_LAST', $curtime, $cacheTime);
        $newCache = serialize($yearPayData);
        $newCache = base64_encode($newCache);
        $cache->set('YLOG_CACHE', $newCache, $cacheTime);
    } else {
        //take data from cache
        if (isset($yearPayData[$year]['graphs'])) {
            $result = $yearPayData[$year]['graphs'];
            $result .= __('Cache state at time') . ': ' . date("Y-m-d H:i:s", ($renewTime)) . ' ';
            $result .= wf_Link("?module=logistis2&forcecache=true", wf_img('skins/icon_cleanup.png', __('Renew')), false, '');
        } else {
            $result = __('Strange exeption');
        }
    }


    show_window(__('Expenses by') . ' ' . $year, $result);
}


/* Show filter controls */
function web_MenuShow($show_year){
		//Date selector form
		$dateSelectorPreset = (wf_CheckPost(array('showdateoperations'))) ? $_POST['showdateoperations'] : curdate();
        $dateinputs = wf_DatePickerPreset('showdateoperations', $dateSelectorPreset);
        $dateinputs .= wf_Submit(__('Show'));
        $dateform = wf_Form("?module=logistis2", 'POST', $dateinputs, 'glamour');
		
		//Year selector form
		$yearinputs = wf_YearSelectorPreset('yearsel', '', false, $show_year);
        $yearinputs .= wf_Submit(__('Show'));
        $yearform = wf_Form("?module=logistis2", 'POST', $yearinputs, 'glamour');
		
		//Menu labels
		$controlcells = wf_TableCell(wf_tag('h3', false, 'title') . __('Year') . wf_tag('h3', true));
        $controlcells .= wf_TableCell(wf_tag('h3', false, 'title') . __('Operations by date') . wf_tag('h3', true));
        $controlcells .= wf_TableCell(wf_tag('h3', false, 'title') . __('Operations search') . wf_tag('h3', true));
        $controlcells .= wf_TableCell(wf_tag('h3', false, 'title') . __('Analytics') . wf_tag('h3', true));
        //$controlcells .= wf_TableCell(wf_tag('h3', false, 'title') . __('ARPU') . wf_tag('h3', true));

        $controlrows = wf_TableRow($controlcells);
		
		//Menu controls
		$controlcells = wf_TableCell($yearform);
        $controlcells .= wf_TableCell($dateform);
        $controlcells .= wf_TableCell(wf_Link("?module=logistis_search", web_icon_search() . ' ' . __('Find'), false, 'ubButton'));
        $controlcells .= wf_TableCell(wf_Link("?module=logistis2&analytics=true", web_icon_charts() . ' ' . __('Show'), false, 'ubButton'));
        //$controlcells .= wf_TableCell(wf_Link("?module=report_arpu", wf_img('skins/ukv/report.png') . ' ' . __('Show'), false, 'ubButton'));

        $controlrows .= wf_TableRow($controlcells);
		
		$controlgrid = wf_TableBody($controlrows, '100%', 0, '');
        show_window(__('Filter'), $controlgrid);	
}

function wf_SelectorSort($name, $params, $label, $selected = '', $br = false, $sort = false, $CtrlID = '', $CtrlClass = '', $options = ''){
	$inputid = ( empty($CtrlID) ) ? wf_InputId() : $CtrlID;
    $inputclass = ( empty($CtrlClass) ) ? '' : ' class="' . $CtrlClass . '"';
    $opts = ( empty($options)) ? '' : ' ' . $options . ' ';

    if ($br) {
        $newline = '<br>';
    } else {
        $newline = '';
    }
    $result = '<select name="' . $name . '" id="' . $inputid . '"' . $inputclass . $options . '>';
    if (!empty($params)) {
        ($sort) ? asort($params) : $params;
        foreach ($params as $value => $eachparam) {
				if($value == $selected){
					$flag_selected = 'SELECTED';
				} else {
				$flag_selected = '';
				}
            //$flag_selected = (($selected == $value) AND ( $selected != '')) ? 'SELECTED' : ''; // !='' because 0 values possible
			$result .= '<option value="' . $value . '" ' . $flag_selected . '>' . $eachparam . '</option>' . "\n";
        }
    }

    $result .= '</select>' . "\n";
    if ($label != '') {
        $result .= '<label for="' . $inputid . '">' . __($label) . '</label>';
    }
    $result .= $newline . "\n";
    return ($result);
}

	// Returns category selection dropdown
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
	$result = wf_Selector('catid',$cats,__('Category'),$current,false,false);
	return($result);
	}
	// Returns company selection dropdown
		function web_CompanySelector($current){
			$query = "SELECT `id`,`company_name` FROM `logistis_companies`";
			$result='';
			$company = array();
			$allcompanies = simple_queryall($query);
		
			if(!empty($allcompanies)){
				$company[0] = __('None');
				foreach($allcompanies as $io => $each){
					$company[$each['id']] = __($each['company_name']);
				}
			}
			
			//$result = wf_SelectorTree('company_id',$company,__('Company'),$current,false,true);
			$result = wf_SelectorSort('company_id',$company,__('Company'),0,false,true);
			return($result);	
		}
	
	/* A money counter */
	function web_MoneyShow(){
		$cashTotal = 0;
		$expCashTotal = 0;
		
		// Getting all payments
		$query = "SELECT `login`,`date`,`summ`,`note` FROM `payments` WHERE `cashtypeid` IN (1,5,7) ORDER BY `date` DESC";
		$allpayments = simple_queryall($query);
		
		//Getting all expenses
		$exp_query = "SELECT `date`,`summ`,`comment`,`user` FROM `expenses` ORDER BY `date` DESC";
		$allexpenses = simple_queryall($exp_query);	

		//Count total Expenses
		if(!empty($allexpenses)){
			foreach($allexpenses as $eachexpense){
				$expCashTotal = $expCashTotal + $eachexpense['summ'];
			}
		}
		
		if(!empty($allpayments)){
			foreach($allpayments as $eachpayment){
				if(!preg_match("/\ECHARGE\b/",$eachpayment['note'])){ //Не учитывать начисления пользователю, которые являются продажами
					$cashTotal = $cashTotal + $eachpayment['summ'];
				}
			}
		}
		
		$cells = wf_TableCell("Расходов за всё время: ");
		$cells.= wf_TableCell($expCashTotal);
		$rows = wf_TableRow($cells,'row3');;
		
		$cells= wf_TableCell("Доходов за всё время: ");
		$cells.= wf_TableCell($cashTotal);		
		$rows.= wf_TableRow($cells,'row3');;
		
		$cells= wf_TableCell("Прибыль: ");
		$cells.= wf_TableCell($cashTotal-$expCashTotal);		
		$rows.=wf_TableRow($cells,'row1');;
	
		
		
		$result = wf_TableBody($rows,'100%');
		
		return $result;
	}
	
	/* Operations menu */
	function web_OpMenuShow(){
		$forminputs = wf_HiddenInput('addexpense','true');
		$forminputs.= wf_TextInput('sum',__('Sum'),'',true,'10');
		$forminputs.= wf_delimiter();
		$forminputs.= web_CatSelector(0);
		$forminputs.= wf_delimiter();
		$forminputs.= web_CompanySelector(0);
		$forminputs.= wf_delimiter();
		$forminputs.= wf_TextArea('note',__('Note'),'',true,'30x5');
		$forminputs.= wf_Submit(__('Add'));
		$form_addexp = wf_Form('','POST',$forminputs,'glamour');
	
		$controlcells =wf_TableCell(wf_Modal(__('Add expense'),__('Expense'),$form_addexp,'ubButton','400','400'));
		$controlcells.=wf_TableCell(wf_Link("?module=logistis_settings",web_icon_settings().' '.__('Settings'),false,'ubButton'));
		$controlcells.=wf_TableCell(wf_Modal(__('Бабло'),__('Бабло'),web_MoneyShow(),'ubButton','400','400'));
		$controlrows =wf_TableRow($controlcells);	
		$controlgrid = wf_TableBody($controlrows, '100%',0,'');
		show_window(__('Operations'),$controlgrid);
	}

	/* Extracts all categories from assignments table */
	function exp_assignments(){
		$result = array();
	
		$query_allcats = "SELECT * FROM `logistis_categories`";
		$allcats = simple_queryall($query_allcats);
	
	
		if(!empty($allcats)){
			foreach ($allcats as $io => $eachcat){
				$result[$eachcat['id']] = $eachcat['category'];
			}
		}
		return($result);
	}
	
	//Adds expense into `expenses` table
	//Adds category assignment
	function ConfirmAddExpense($sum,$note,$catid){
		$cells = wf_TableCell('Sum','10%');
		$cells.= wf_TableCell('Category','10%');
		$cells.= wf_TableCell('Note');		
		$rows = wf_TableRow($cells,'row1');

		$cells = wf_TableCell($sum);
		$cells.= wf_TableCell(CategoryName($catid));
		$cells.= wf_TableCell($note);
		$rows .= wf_TableRow($cells, 'row3');
		
		$forminputs = wf_HiddenInput('confirm','true');
		$forminputs.= wf_HiddenInput('sum',$sum);
		$forminputs.= wf_Submit(__('Confirm'));
		
		$result = wf_TableBody($rows,'50%',0,'');
		$result.= wf_Form('','POST',$forminputs,'glamour');
		return($result);
	}
	
	function AddExpense($sum,$note,$catid,$company_id=0){
		$whoami = whoami();
		//Insert an expense
		$query = "INSERT INTO `expenses` (`date`,`summ`,`comment`,`user`) VALUES (NOW(), '$sum', '$note', '$whoami')";
		nr_query($query);
		
		//Insert expense category
		$exp_lastid = simple_get_lastid('expenses');
		$query = "INSERT INTO `logistis_categories` (`exp_id`,`cat_id`) VALUES ('$exp_lastid','$catid') ";
		nr_query($query);
		
		//Insert expense company
		if(empty($company_id)){
			$company_id = 0;
		}
		$query = "INSERT INTO `logistis_company_assignments` (`exp_id`,`company_id`) VALUES ('$exp_lastid','$company_id')";
		nr_query($query);
		
		
		rcms_redirect("?module=logistis2");
	}
	

	
	//Converts category ID into name
	function CategoryName($catid){
		$query = "SELECT * FROM `logistis_cattypes` WHERE `id`=$catid";
		$category = simple_queryall($query);
		
		if(!empty($category)){
			foreach($category as $io => $each){
				$result = $each['category'];
			}
			return($result);
		}
	}

	//Extracts categories from assignments table by expense ID and replaces category ID with category name
	//
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
	
	//Returns assigned company id by exp_id
	function get_CompanyByOpId($exp_id){
		if(!empty($exp_id)){
			$query = "SELECT `company_id` FROM `logistis_company_assignments` WHERE `exp_id`=$exp_id";
			$result = simple_queryall($query);
			
			if(!empty($result)){
				foreach($result as $row){
					return $row['company_id'];
				}	
			} else {
				//Category assignment does not exist, create it
				$query = "INSERT INTO `logistis_company_assignments` (`exp_id`,`company_id`) VALUES ('$exp_id','0')";
				$result = nr_query($query);
			}
		}	
	}
	
	function get_CompanyNameByOpId($exp_id){
		$query = "SELECT * FROM `logistis_company_assignments` INNER JOIN (SELECT * FROM `logistis_companies`) AS company ON (`logistis_company_assignments`.`company_id`=`company`.`id`) WHERE `exp_id`='$exp_id'";
		$company = simple_queryall($query);
		
		if(!empty($company)){
			foreach($company as $each){
				$result = $each['company_name'];
			}
			return($result);
		}
	}
		
		/**************************************/		
		/********* INVOICE FUNCTIONS **********/
		/**************************************/
		//Returns Customer ID by realname 
		
		//Returns company ID by name
		
		//Returns item id by model name
		
		// Invoice menu
		
		/* Returns array of company names */
		function db_AllCompanies(){
			$result = array();
			$query = 'SELECT * FROM `logistis_companies`';
			$allcompanies = simple_queryall($query);
			if(!empty($allcompanies)){
				foreach($allcompanies as $ArrayData){
					$result[$ArrayData['id']]=$ArrayData['company_name'];
				}
			}
			
			return($result);
		}
		
		//Returns realnames array for quick input search
		function AllRealNames(){
			$allrealnames = zb_UserGetAllRealnames();
			return($allrealnames);
		}
		
		/*Returns customer quick search */
		function web_RealNameQuickSearch(){
			$inputs = wf_AutocompleteTextInput('customer_name',AllRealNames(),'Real name', '', false, 30);
			return $inputs;
		}
		
		/* Returns quick search form */
		function web_CompanyQuickSearch(){
			
			$inputs = wf_AutocompleteTextInput('companyname',db_AllCompanies(), 'Company name','', false, 30);
			
			return $inputs;
		}
		
		/* Returns complete item list form db */
		function db_AllItems(){
			$result = array();
			$query = "
				SELECT `logistis_opmakes`.`id`,`logistis_opmakes`.`make`,`logistis_opmodels`.`id`,`logistis_opmodels`.`model` FROM `logistis_opmakes` 
				INNER JOIN `logistis_opmodels` ON `logistis_opmakes`.`id`=`logistis_opmodels`.`make_id`";
			$allItems = simple_queryall($query);
			
			if(!empty($allItems)){
				foreach($allItems as $ArrayData){
					$result[$ArrayData['id']]=$ArrayData['make'] . ' ' . $ArrayData['model'];
				}
			}
			
			return $result;
		}
		
		/* Returns items quick search */
		function web_ItemQuickSearch(){
			//echo json_encode(db_AllItems());
			$inputs = wf_AutocompleteTextInput('itemdata',db_AllItems(),'Item search','', false, 30);
			
			return $inputs;
		}
		
		/// Generates invoices for payments with specified type ids
		function GenerateInvoices(){
			$whoami = whoami();
			$hellenic = 8; //Hellenic
			$mypos = 7;
			$note = '';
			//$query = "SELECT `id`,`login`,`cashtypeid` FROM `payments` WHERE DATE_FORMAT(`date`, '%Y-%m-%d')=CURDATE() AND `cashtypeid` IN ('$hellenic','$mypos')";
			$query = "SELECT `payments`.`id`,`realname`.`realname` FROM `payments` INNER JOIN `realname` ON `realname`.`login`=`payments`.`login` WHERE DATE_FORMAT(`date`, '%Y-%m-%d')=CURDATE() AND `cashtypeid` IN ('$hellenic','$mypos')";
			$genpayments = simple_queryall($query);
			
	
			
			if(!empty($genpayments)){
				$invoice_status = 1;
				
				foreach($genpayments as $eachpayment){
					$payid = $eachpayment['id'];
					$realname = $eachpayment['realname'];
					if(logis_UniqueInvoiceCheckup($payid)==1){
						$invoice_num = date("Y").sprintf('%04d',getLastInvoiceId()+1);
						$query = "INSERT INTO `logistis_invoices` (`payment_id`,`number`,`status`,`date`,`note`,`user`,`realname`) VALUES ('$payid','$invoice_num','$invoice_status',NOW(),'$note','$whoami','$realname')";
						nr_query($query);
					}
				}
				rcms_redirect("?module=logistis2");
			}
		}
		
		/* Returns menu for the invoice tools */
		function web_InvoiceMenu(){
			//Invoice creation form
			$forminputs = wf_HiddenInput('createinvoice','true');
			$forminputs.= wf_TextInput('payid',__('Payment id'),'',true,'10');
			$forminputs.= wf_delimiter();
			$forminputs.= wf_TextArea('note',__('Note'),'',true,'30x5');
			$forminputs.= wf_delimiter();
			$forminputs.= wf_Submit(__('Add'));
			$form_createinvoice = wf_Form('','POST',$forminputs,'glamour');
			
			//Proforma creation form
			$forminputs = wf_HiddenInput('createproforma','true');
			$forminputs.= web_CompanyQuickSearch();
			$forminputs.= wf_delimiter();
			$forminputs.= web_RealNameQuickSearch();
			$forminputs.= wf_delimiter();
			$forminputs.= web_ItemQuickSearch();
			$forminputs.= wf_delimiter();
			$forminputs.= wf_Submit(__('Add'));
			$form_createproforma = wf_Form('','POST',$forminputs,'glamour');
			
			//Invoice generator
			$forminputs = wf_HiddenInput('generateinvoices','true');
			$forminputs.= __('This action will create lacking invoices for today payments with according payment types.');
			$forminputs.= wf_delimiter();
			$forminputs.= __('Are you sure you want to do this?');
			$forminputs.= wf_Submit(__('Generate'));
			
			$form_generateinvoices = wf_Form('','POST',$forminputs,'glamour');
			
	
			$controlcells=wf_TableCell(wf_Modal(wf_img('skins/add_icon.png') . ' ' .__('Create Proforma'),__('Proforma Invoice'),$form_createproforma,'ubButton','600','400'),'20%');
			$controlcells.=wf_TableCell(wf_Modal(wf_img('skins/add_icon.png') . ' ' .__('Create invoice'),__('Invoice'),$form_createinvoice,'ubButton','600','400'));
			$controlcells.=wf_TableCell(wf_Modal(wf_img('skins/diff_icon.png') . ' ' .__('Invoice generator'),__('Invoice generator'),$form_generateinvoices,'ubButton','600','400'));
			
			//$controlcells.=wf_TableCell(wf_Link("?module=logistis_invoice_settings",web_icon_settings().' '.__('Settings'),false,'ubButton'));
			$controlrows =wf_TableRow($controlcells);	
			$controlgrid = wf_TableBody($controlrows, '100%',0,'');
			show_window(__('Invoices'),$controlgrid);			
		}
		
		
		//Returns last invoice id
		function getLastInvoiceId(){
			$lastid = simple_get_lastid("logistis_invoices");
			return $lastid;
		}
		
		//Return 1 if invoice is cleared to be unique
		function logis_UniqueInvoiceCheckup($payid){
			$query = "SELECT * FROM `logistis_invoices` WHERE `payment_id`='$payid'";
			$result = simple_queryall($query);
				
			if(!empty($result)){
				return 0;
			} else {
				return 1;
			}		
		}
		
		//Invoice creation
		function CreateInvoice($payid,$note){
			$whoami = whoami();
			
			//Aquire current customer name
			$query = "SELECT `realname` FROM `realname` INNER JOIN `payments` ON `payments`.`login`=`realname`.`login` WHERE `payments`.`id`='$payid'";
			$namedata = simple_query($query);
			$realname = $namedata['realname'];
			
			//Invoice number is calculated from the current year + invoice order ID
			
			$invoice_num = date("Y").sprintf('%04d',getLastInvoiceId()+1);
			$invoice_status = 0;
			
			if(logis_UniqueInvoiceCheckup($payid)==1){
				$query = "INSERT INTO `logistis_invoices` (`payment_id`,`status`,`date`,`note`,`user`,`realname`) VALUES ('$payid','$invoice_status',NOW(),'$note','$whoami','$realname')";
				nr_query($query);
			} else {
				wf_Modal(__('Add expense'),__('Expense'),0,'ubButton','400','400');
			}
			
			rcms_redirect("?module=logistis2");
		}
		

		
		/*** Function that displays invoices table in a list
		/***
		/***
		/***
		/***
		***/
		function web_InvoicesShow($query){
			$totalInvoiceCount = 0;
			$allinvoices = simple_queryall($query);
			
			$cells = wf_TableCell("ID");
			$cells.= wf_TableCell("Payment ID");
			$cells.= wf_TableCell("Customer Name");
			$cells.= wf_TableCell("Invoice Number");
			$cells.= wf_TableCell("Date");
			$cells.= wf_TableCell("Description");
			$cells.= wf_TableCell("Status");
			$rows = wf_TableRow($cells,'row1');
			
			if(!empty($allinvoices)){
				foreach($allinvoices as $io => $eachinvoice){
					//$invoice_num = date("Y").sprintf('%04d',$eachinvoice['id']);
					$invoice_num = substr($eachinvoice['date'],0,4).sprintf('%04d',$eachinvoice['id']);
					
					$cells = wf_TableCell($eachinvoice['id']);
					$cells.= wf_TableCell($eachinvoice['payment_id']);
					$cells.= wf_TableCell($eachinvoice['realname']);
					$cells.= wf_TableCell("<a href='?module=logistis_invoice&id=".$eachinvoice['id']."'>".$invoice_num."</a>");
					$cells.= wf_TableCell($eachinvoice['date']);
					$cells.= wf_TableCell($eachinvoice['note']);
					$cells.= wf_TableCell($eachinvoice['status']);
					$rows.= wf_TableRow($cells,'row5');
					
					$totalInvoiceCount++;
				}
			}
			
			
			$result = wf_TableBody($rows, '100%', '0', 'sortable');
			$result .= wf_tag('strong') . __('Count') . ': ' . $totalInvoiceCount . wf_tag('strong', true);
			return($result);			
		}
		
		/******************************/
		/*** END INVOICES FUNCTIONS ***/
		/******************************/
		
		//Set specific year if selected
		 if (!wf_CheckPost(array('yearsel'))) {
			 if(wf_CheckGet(array('month'))){
				$show_year = stripslashes(substr($_GET['month'],0,4));
			 } else {
				$show_year = curyear();
			 }
        } else {
            $show_year = $_POST['yearsel'];
        }
		
		if(isset($_POST['generateinvoices'])){
			GenerateInvoices();
		}
		
		
		web_OpMenuShow();
		web_MenuShow($show_year);
		web_ExpensesShowGraph($show_year);

		
		// payments by somedate
		if(!isset($_GET['month'])){
            if (isset($_POST['showdateoperations'])) {
                $paydate = mysql_real_escape_string($_POST['showdateoperations']);
                $paydate = (!empty($paydate)) ? $paydate : curdate();
                //$fixerControl = (cfr('ROOT')) ? wf_Link('?module=paymentsfixer', ' ' . wf_img('skins/icon_repair.gif', __('Unprocessed payments repair'))) : '';
                show_window(__('Payments by date') . ' ' . $paydate, web_ExpensesShow("SELECT * from `expenses` WHERE `date` LIKE '" . $paydate . "%' ORDER by `date` DESC;"));
            } else {

			// today payments
                $today = curdate();
                show_window(__('Today expenses'), web_ExpensesShow("SELECT * from `expenses` WHERE `date` LIKE '" . $today . "%' ORDER by `date` DESC;"));
            }
		}else {
			// show monthly operations
            $opmonth = mysql_real_escape_string($_GET['month']);

            show_window(__('Month payments'), web_ExpensesShow("SELECT * from `expenses` WHERE `date` LIKE '" . $opmonth . "%'  ORDER by `date` DESC;"));
		}

	
	
	}	else {
		//User has no access to this module
		show_error(__('You cant control this module'));
	}
	
	
	web_InvoiceMenu();
	
	//Invoices listing
	if(!isset($_GET['invmonth'])){
		if(isset($_POST['showdateinvoices'])){
			$invdate = mysql_real_escape_string($_POST['showdateinvoices']);
			$invdate = (!empty($invdate)) ? $invdate : curdate();
			
			show_window(__('Today Invoices'), web_InvoicesShow("SELECT * FROM `logistis_invoices` WHERE `date` LIKE '" . $invdate . "%' ORDER by `date` DESC;"));
		} else {
			if(!isset($_GET['month'])){
				//Today invoices 
				$today = curdate();
				show_window(__('Today invoices'), web_InvoicesShow("SELECT * FROM `logistis_invoices` WHERE `date` LIKE '" . $today . "%' ORDER by `date` DESC"));
			} else {
				$month = mysql_real_escape_string($_GET['month']);
				show_window(__('This month invoices'), web_InvoicesShow("SELECT * FROM `logistis_invoices` WHERE `date` LIKE '" . $month . "%' ORDER by `date` DESC"));
				//show_window(__('This month invoices'), web_InvoicesShow("SELECT * FROM `logistis_invoices` INNER JOIN `payments` ON `logistis_invoices`.`payment_id`=`payments`.`id` INNER JOIN `realname` ON `payments`.`login`=`realname`.`login` WHERE `date` LIKE '" . $month . "%' ORDER by `date` DESC"));
			}
		}
	}
	
	//POST requests handlers
	//Add expense if there is a request
	if(wf_CheckPost(array('addexpense'))){
		$sum = mysql_real_escape_string($_POST['sum']);
		$note = mysql_real_escape_string($_POST['note']);
		$catid = mysql_real_escape_string($_POST['catid']);
		$company_id = mysql_real_escape_string($_POST['company_id']);
		show_window('Adding expense..',AddExpense($sum,$note,$catid, $company_id));
	}
	//Create invoice request
	if(wf_CheckPost(array('createinvoice'))){
		$payid = mysql_real_escape_string($_POST['payid']);
		$note = mysql_real_escape_string($_POST['note']);
		if($payid==null){ $payid = 0; }
		show_window('Creating invoice..',CreateInvoice($payid,$note));
	}
	if(wf_CheckPost(array('createproforma'))){
	echo json_encode($_POST);
	}
?>