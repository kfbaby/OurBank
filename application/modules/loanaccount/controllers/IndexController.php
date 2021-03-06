<?php
/*
############################################################################
#  This file is part of OurBank.
############################################################################
#  OurBank is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as
#  published by the Free Software Foundation, either version 3 of the
#  License, or (at your option) any later version.
############################################################################
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU Affero General Public License for more details.
############################################################################
#  You should have received a copy of the GNU Affero General Public License
#  along with this program.  If not, see <http://www.gnu.org/licenses/>.
############################################################################
*/
class Loanaccount_IndexController extends Zend_Controller_Action 
{
    public function init() 
    {
        $this->view->pageTitle = 'Loans';
        $this->view->title = 'Accounting';
	 $storage = new Zend_Auth_Storage_Session();
        $data = $storage->read();
        if(!$data){
                $this->_redirect('index/login'); // once session get expired it will redirect to Login page
        }


        $sessionName = new Zend_Session_Namespace('ourbank');
        $userid=$this->view->createdby = $sessionName->primaryuserid; // get the stored session id 

        $login=new App_Model_Users();
        $loginname=$login->username($userid);
        foreach($loginname as $loginname) {
            $this->view->username=$loginname['username']; // get the user name
        }

        $this->view->accounts = new Loanaccount_Model_Accounts();
        $this->view->cl = new App_Model_dateConvertor ();
        $this->view->adm = new App_Model_Adm ();
    }

    public function indexAction() 
    {
        $accountsForm = $this->view->form = new Loanprocess_Form_Search();
         $loanaccount = new Loanaccount_Model_Accounts();

        if($_POST)
            $postedvalues = $this->view->adm->commonsearchquery($_REQUEST,1);
	else
	   $postedvalues = $this->view->adm->commonsearchquery($_REQUEST,2); 

         $result = $loanaccount->search($postedvalues);
		$this->view->loanaccount = $result;
//print_r($result);
        $page = $this->_getParam('page',1);
        $this->view->paginator = $this->view->adm->commonsearch($result,$page);
        $this->view->requestvalues=$this->view->adm->encodedvalue($postedvalues);
          if (!$result){
                       echo "<font color='RED'>Records Not Found Try Again...</font>";
                            }

	}

// //         if ($this->_request->isPost() && $this->_request->getPost('Submit')) {
// // 	    $formData = $this->_request->getPost();
// // 	    if ($this->_request->isPost()) {
// // 		$formData = $this->_request->getPost();
// // 		if ($accountsForm->isValid($formData)) {
// // 		    $this->view->result = $this->view->accounts->search($this->_request->getParam('membercode'));
// // 		}
// //             }
// //         }
// //     }

    public function detailsAction() 
    {
        $code = base64_decode($this->_request->getParam('code'));
        $this->view->result = $this->view->accounts->getDetails($code);
        $this->view->code = $code;
        $this->view->products = $this->view->accounts->fetchLoanProducts($code);
        $this->view->account = $this->view->accounts->accountsSearch($code);
    }

    public function createaccountAction()
    {
    	$productId = base64_decode($this->_request->getParam('Id'));
    	$code = base64_decode($this->_request->getParam('code'));
        $this->view->account = $this->view->accounts->details($productId,$code);
        $this->view->interestRates = $this->view->accounts->getInterestRates($productId);
        foreach ($this->view->account as $account) {
            $minDeposite = $account->minamount; // Validate for min balance
            $maxDeposite = $account->maxamount;
            $minInstallments = $account->minInstallments; 
            $maxInstallments = $account->maxInstallments;
            $typeID = $account->typeID;
            $requetloan=$account->requestamount;
        }
        if($typeID == 2 or $typeID == 3)
        {
        $grouprequest=$this->view->accounts->getloanrequest($code);
        $requetloan = $grouprequest[0]['requestamount'];
        }
        if($requetloan<=$maxDeposite && $requetloan>=$minDeposite)
        {
           $maxloanamount = $requetloan;
           $messageerr='Loan amount is less than or equal requested amount ='.$maxloanamount;
        }
        else
        {
           $maxloanamount = $maxDeposite;
           $messageerr='Maximum Amount To open a loan account ='.$maxDeposite;
        }
        $app = $this->view->baseUrl();
        $loanForm = new Loanaccount_Form_Loans($minDeposite,$requetloan,$maxDeposite,$this->_request->getParam('Id'),$this->_request->getParam('code'),$app,$messageerr);
        for($i=$minInstallments;$i<=$maxInstallments;$i++)  {
		$loanForm->installments->addMultiOption($i,$i);
	}
	$interesttypes = $this->view->adm->viewRecord('ourbank_interesttypes','id','ASC');
	foreach($interesttypes as $interesttypes) {
		$loanForm->interesttype_id->addMultiOption($interesttypes->id,$interesttypes->description);
	}
// 	$acc = $this->view->accounts->savingAcc($code);
// 	foreach($acc as $acc) {
// 		$loanForm->savingAccount->addMultiOption($acc->id,$acc->account_number);
// 	}
	
	$funder = $this->view->adm->viewRecord('ourbank_funder','id','ASC');
	foreach($funder as $funder) {
		$loanForm->funders->addMultiOption($funder->id,$funder->name);
	}
	$this->view->feedetails = $this->view->adm->viewRecord('ourbank_fee','id','ASC');
	$countvalue=count($this->view->feedetails);
// 	foreach($fee as $fee) {
// 		$loanForm->fee->addMultiOption($fee->id,$fee->name);
// 	}
	
        $this->view->loanForm = $loanForm;
        if ($this->_request->isPost() && $this->_request->getPost('Submit')) {
	    $formData = $this->_request->getPost();
 		if ($loanForm->isValid($formData)) {
		    foreach ($this->view->account as $account) {
		        $begindate = $account->begindate;
		        $closedate = $account->closedate;
		        $officeid = $account->officeid;
		        $typeID = $account->typeID;
		        $glsubID = $account->glsubID;
			$memberId = $account->memberId;
		    }
		    if ($this->view->cl->phpmysqlformat($this->_request->getPost('date')) < $begindate) {
		          $this->view->maxdate= "Date of account should be after - ".$this->view->cl->phpnormalformat($begindate) ;
		    } else if ($this->view->cl->phpmysqlformat($this->_request->getPost('date')) > $closedate) {
		          $this->view->maxdate= "Date of account should be before - ".$this->view->cl->phpnormalformat($closedate) ;
// 
		    }
		      else {
		        // Insertion into ourbank_account 
		        $data = array('account_number' => 1,
                                      'member_id' => $memberId,
                                      'product_id' => $productId,
                                      'begin_date' => $begindate,
                                      'close_date' => $closedate,
                                      'membertype_id' => $typeID,
                                      'accountcreated_date'=> $this->view->cl->phpmysqlformat($this->_request->getPost('date')),
                                      'created_by' => 1,
                                      'status_id'=>3);
				$accId = $this->view->adm->addRecord('ourbank_accounts',$data);
		        // Account number formation 
		        // <-----------14 digit number ---------->
		        // <--3-->/<--2-->/<---->/<--3-->/<--6-->
		        // 00office_id/0membertype_id/00typeofacc [L/S/F/R]/00000account_id
		    	$b=str_pad($officeid,3,"0",STR_PAD_LEFT); 
			$t=str_pad($typeID,2,"0",STR_PAD_LEFT);
		    	$p=str_pad($productId,2,"0",STR_PAD_LEFT);
		    	$i = "L";
		        $a=str_pad($accId,6,"0",STR_PAD_LEFT);
                        $account = array('account_number' =>$b.$t.$p.$i.$a);
                        $this->view->accounts->accUpdate($accId,$account);
		        //Insertion into loan account
                        if($this->_request->getPost('fundings')==0)
                        {
                            $funderid=$this->_request->getPost('fundings');
                        }
                        else
                        {
                            $funderid=$this->_request->getPost('funders');
                        }

		        $input = array('account_id' => $accId,
                                      'funder_id' => $funderid,
                                      'loansanctioned_date' => $this->view->cl->phpmysqlformat($this->_request->getPost('date')),
                                      'loan_amount' => $this->_request->getPost('amount'),
                                      'loan_installments' => $this->_request->getPost('installments'),
                                      'loan_interest' => $this->_request->getPost('interest'),
                                      'interesttype_id' => $this->_request->getPost('interesttype_id'),
                                   //   'savingsaccount_id' => $this->_request->getPost('savingAccount'),
                                      'tieup_flag' => 0,
                                      'created_by' => 1);
      		        $this->view->adm->addRecord('ourbank_loanaccounts',$input);

			for($j=1;$j<=$countvalue;$j++){
			if($this->_request->getParam('fee-'.$j)){
                            $feeInput = array('account_id' => $accId,
                                              'fee_id' => $this->_request->getParam('fee-'.$j), 'feeamount'=>$this->_request->getParam('hidden-'.$j));
			//print_r($feeInput);
                           $this->view->adm->addRecord('ourbank_accountfee',$feeInput);
			}
			}


                        $memberlist=$this->view->accounts->getmemberlist($memberId,$typeID);
                        if($memberlist){
                        foreach($memberlist as $memberid) 
                        {
                        $this->view->accounts->Updatestatus($memberid['memberid'],$typeID);
                        }
                        }
		        $this->_redirect("/loanaccount/index/message/acNum/".base64_encode($b.$t.$p.$i.$a));
		    }
		}
        }
    }
    	
    public function interestAction() 
    {
	$this->_helper->layout()->disableLayout();
	$interest = $this->view->accounts->getInterest($this->_request->getParam('productId'),$this->_request->getParam('interest'));
	foreach ($interest as $interest) {
    	    $this->view->id = $interest->id;
	    $this->view->interest = $interest->interest;
	}
   }

    public function feeamountAction()
    {
	$this->_helper->layout()->disableLayout();
        $feeid=$this->_request->getParam('feeid');
        $loanamount=$this->_request->getParam('loanamount');
        $this->view->hiddenid=$this->_request->getParam('hidden');
        $feedetails=$this->view->accounts->getfee($feeid); //print_r($feedetails);
        if($feedetails[0]['feetype_id']==1) {
        $feeamount=$this->view->feeamount=$feedetails[0]['value'];
        echo "<font color=green>(".$feedetails[0]['value'].")</font>";
        }
        else {
        $feeamount=$this->view->feeamount=($feedetails[0]['value']/100)*$loanamount;
        echo "<font color=green>(".$feeamount.")</font>";
        }
    }

    public function messageAction() 
    {
        $this->view->pageTitle = 'Accounting';
	$this->view->acNum = base64_decode($this->_request->getParam('acNum'));
    }
}

