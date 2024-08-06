<?php
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 0); 
class Cron extends CI_Controller {
	private $beeKey;
	private $_owner;
	private $_beeParams;
	
	public function __construct() {
		parent::__construct();
		$this->load->model('ino_item');
		$this->load->model('ino_item_vendor');
		$this->load->model('ino_gudang');
		$this->load->model('ino_stock');
		$this->load->model('ino_sync');

		//if(!is_cli()) show_404();

		$this->beeKey = 'some private key';
		//show_404();
		$this->_owner = 'tokopedia';

		$headers['Content-Type'] =  'application/json';
        $headers['Authorization'] =  'Bearer '.$this->beeKey;

        $this->_beeParams = [
            // Base URI is used with relative requests
            'base_uri' => 'https://app.beecloud.id/api/v1/',
            // You can set any number of default request options.
            'timeout'  => 100.0,
            'headers' => $headers
		];
	}

	public function index() {
		$this->updateHargaVendor();
		$this->insertStock();
		
	}

	//DELETE TABLE item
	//INPUT TABLE item
	//FROM beecloud
    public function insertItem(){
		
		$this->load->library('ss_guzzle',$this->_beeParams);

        $r = $this->ss_guzzle->client->request('GET', 'item');
        $response = json_decode($r->getBody()->getContents(),true);
		$data=$response['data'];
		
        for($i=0;$i<count($data);$i++){
// if($data[$i]['code']=='3408005') var_dump($data[$i]);
			unset($dt);
			$dt['item_vendor_id'] = $data[$i]['id'];
			$dt['item_vendor_sku'] = $data[$i]['code'];
			$dt['item_vendor_nama'] = $data[$i]['name1'];
			$dt['item_vendor_owner'] = $this->_owner;

			$res[] = $dt;
        
        }
		
		if($res) {
			$this->ino_item_vendor->loadData($this->_owner,$res);
			$this->ino_item->setNama();
			$this->setIdItem();
		}
	}

	public function setIdItem(){
		$data = $this->ino_item_vendor->getUnAssign($this->_owner);
		for($i=0;$i<count($data);$i++){
			$item=array();
        	$item['item_vendor_id']=($data[$i]->item_vendor_id);
			$item['item_sku']=$data[$i]->item_vendor_sku;
			$item['item_nama'] = $data[$i]->item_vendor_nama;
			$item['item_owner'] = $this->_owner;
			
			//var_dump($response['data'][$i]['name1']);
			$res[] = $item;
				$this->ino_item->insertitem($item);
	}

	}


	//DELETE TABLE gudang
	//INPUT TABLE gudang
	//FROM beecloud
	public function insertGudang(){

		$this->load->library('ss_guzzle',$this->_beeParams);

        $r = $this->ss_guzzle->client->request('GET', 'wh');
        $response = json_decode($r->getBody()->getContents(),true);
		   $data=$response['data'];

		   for($i=0;$i<count($data);$i++){
        	$gudang=array();
        	$gudang['gudang_id']=($data[$i]['id']);
			$gudang['gudang_nama']=$data[$i]['name'];
			$gudang['gudang_kode']=$data[$i]['code'];
			$gudang['gudang_owner'] = $this->_owner;
			//var_dump($response['data'][$i]['name1']);
			$this->ino_gudang->insertGudang($gudang);
		}

	}
	


	//DELETE TABLE stock
	//INPUT TABLE stock
	//FROM beecloud
	public function insertStock(){
		$syncid = $this->_owner.'_stock';
        $this->load->library('ss_guzzle',$this->_beeParams);

		$sync = $this->ino_sync->getLast($syncid);
		$url = $sync ? 'stock?lastupdate='.$sync[0]->lastsync : 'stock';
		//$url = 'stock?lastupdate=2022-03-01 00:00:00';
		$this->ino_sync->setLast($syncid);

		$r = $this->ss_guzzle->client->request('GET', $url);
        $response = json_decode($r->getBody()->getContents(),true);
        $data=$response['data'];
		
		for($i=0;$i<count($data);$i++){
			if($data[$i]['wh_id']>3) continue;

			$stock=array();
			/* if($response['data'][$i]['item_id']==32779) {
			 print_r($response['data'][$i]);
			// echo ($response['data'][$i]['wh_id'].','.$response['data'][$i]['pid'].','.$response['data'][$i]['qty'].','.$response['data'][$i]['updated_at'].','.$response['data'][$i]['whid'])."\n";
			 }*/

			$stock['id_gudang'] = $data[$i]['wh_id'];
			$stock['stock_owner'] = $this->_owner;
			$stock['id_item_vendor'] = $data[$i]['item_id'];
        	$stock['sn'] = $data[$i]['pid'];
			$stock['id_item'] = null;
			$stock['stock_quantity'] = $data[$i]['qty'];
			$stock['source_updated_at'] = $data[$i]['updated_at'];
			$stock['cron_updated_at'] = date('Y-m-d H:i:s');
			$stock['sync_log'] = $url;
	
			$this->ino_stock->insertStock($this->_owner,$stock);
        }
		
		}
	
}
/* End of file cron.php */
/* Location: ./application/controllers/cron.php */
