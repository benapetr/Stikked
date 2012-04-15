<?php
/**
 * Class and Function List:
 * Function list:
 * - __construct()
 * - countPastes()
 * - createPaste()
 * - checkPaste()
 * - getPaste()
 * - getLists()
 * - cron()
 * Classes list:
 * - Pastes extends CI_Model
 */

class Pastes extends CI_Model
{
	
	function __construct() 
	{
		parent::__construct();
	}
	
	function countPastes() 
	{
		$this->db->where('private', 0);
		$query = $this->db->get('pastes');
		return $query->num_rows();
	}
	
	function createPaste() 
	{
		$this->load->library('process');
		$data['id'] = NULL;
		$data['created'] = time();

		//this is SO evil… saving the «raw» data with htmlspecialchars :-( (but I have to leave this, because of backwards-compatibility)
		$data['raw'] = htmlspecialchars($this->input->post('code'));
		$data['lang'] = htmlspecialchars($this->input->post('lang'));
		$data['replyto'] = $this->input->post('reply');
		
		if ($this->input->post('name')) 
		{
			$data['name'] = htmlspecialchars($this->input->post('name'));
		}
		else
		{
			$data['name'] = $this->config->item('unknown_poster');
			
			if ($data['name'] == 'random') 
			{
				$nouns = $this->config->item('nouns');
				$adjectives = $this->config->item('adjectives');
				$data['name'] = $adjectives[array_rand($adjectives) ] . " " . $nouns[array_rand($nouns) ];
			}
		}
		
		if ($this->input->post('title')) 
		{
			$data['title'] = htmlspecialchars($this->input->post('title'));
		}
		else
		{
			$data['title'] = $this->config->item('unknown_title');
		}
		$data['private'] = $this->input->post('private');
		do 
		{
			
			if ($this->input->post('private')) 
			{
				$data['pid'] = substr(md5(md5(rand())) , 0, 8);
			}
			else
			{
				$data['pid'] = rand(10000, 99999999);
			}
			$this->db->select('id');
			$this->db->where('pid', $data['pid']);
			$query = $this->db->get('pastes');
			
			if ($query->num_rows > 0 or $data['pid'] == 'download') 
			{
				$n = 0;
				break;
			}
			else
			{
				$n = 1;
				break;
			}
		}
		while ($n == 0);
		
		if ($this->input->post('expire') == 0) 
		{
			$data['expire'] = '0000-00-00 00:00:00';
		}
		else
		{
			$format = 'Y-m-d H:i:s';
			$data['toexpire'] = 1;
			switch ($this->input->post('expire')) 
			{
			case '30':
				$data['expire'] = mktime(date("H") , (date("i") + 30) , date("s") , date("m") , date("d") , date("Y"));
			break;
			case '60':
				$data['expire'] = mktime((date("H") + 1) , date("i") , date("s") , date("m") , date("d") , date("Y"));
			break;
			case '360':
				$data['expire'] = mktime((date("H") + 6) , date("i") , date("s") , date("m") , date("d") , date("Y"));
			break;
			case '720':
				$data['expire'] = mktime((date("H") + 12) , date("i") , date("s") , date("m") , date("d") , date("Y"));
			break;
			case '1440':
				$data['expire'] = mktime((date("H") + 24) , date("i") , date("s") , date("m") , date("d") , date("Y"));
			break;
			case '10080':
				$data['expire'] = mktime(date("H") , date("i") , date("s") , date("m") , (date("d") + 7) , date("Y"));
			break;
			case '40320':
				$data['expire'] = mktime(date("H") , date("i") , date("s") , date("m") , (date("d") + 24) , date("Y"));
			break;
			}
		}
		
		if ($this->input->post('snipurl') == false) 
		{
			$data['snipurl'] = false;
		}
		else
		{
			$url = site_url('view/' . $data['pid']);
			$url = urlencode($url);
			$bitly_username = $this->config->item('bitly_username');
			$bitly_apikey = $this->config->item('bitly_apikey');
			$target = 'http://api.bitly.com/v3/shorten?login=' . $bitly_username . '&apiKey=' . $bitly_apikey . '&longUrl=' . $url;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $target);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$resp = curl_exec($ch);
			curl_close($ch);
			$resp = json_decode($resp);
			$data['snipurl'] = $resp->data->url;
			
			if (empty($data['snipurl'])) 
			{
				$data['snipurl'] = false;
			}
		}
		$data['paste'] = $this->process->syntax($this->input->post('code') , $this->input->post('lang'));
		$this->db->insert('pastes', $data);
		return 'view/' . $data['pid'];
	}
	
	function checkPaste($seg = 2) 
	{
		
		if ($this->uri->segment($seg) == "") 
		{
			return false;
		}
		else
		{
			$this->db->where('pid', $this->uri->segment($seg));
			$query = $this->db->get('pastes');
			
			if ($query->num_rows() > 0) 
			{
				return true;
			}
			else
			{
				return false;
			}
		}
	}
	
	function getPaste($seg = 2, $replies = false) 
	{
		
		if ($this->uri->segment($seg) == '') 
		{
			redirect('');
		}
		else
		{
			$pid = $this->uri->segment($seg);
			$data['script'] = 'jquery.js';
		}
		$this->db->where('pid', $pid);
		$query = $this->db->get('pastes');
		foreach ($query->result_array() as $row) 
		{
			$data['title'] = $row['title'];
			$data['pid'] = $row['pid'];
			$data['name'] = $row['name'];
			$data['lang_code'] = $row['lang'];
			$data['lang'] = $this->languages->code_to_description($row['lang']);
			$data['paste'] = $row['paste'];
			$data['created'] = $row['created'];
			$data['url'] = site_url('view/' . $row['pid']);
			$data['raw'] = $row['raw'];
			$data['snipurl'] = $row['snipurl'];
			$inreply = $row['replyto'];
		}
		
		if ($inreply) 
		{
			$this->db->select('name, title');
			$this->db->where('pid', $inreply);
			$query = $this->db->get('pastes');
			
			if ($query->num_rows() > 0) 
			{
				foreach ($query->result_array() as $row) 
				{
					$data['inreply']['title'] = $row['title'];
					$data['inreply']['name'] = $row['name'];
					$data['inreply']['url'] = site_url('view/' . $inreply);
				}
			}
			else
			{
				$data['inreply'] = false;
			}
		}
		
		if ($this->db_session->flashdata('acopy') == 'true') 
		{
			
			if ($data['snipurl']) 
			{
				$url = $data['snipurl'];
			}
			else
			{
				$url = $data['url'];
			}
			$data['status_message'] = 'URL copied to clipboard';
			$data['insert'] = '
			<script type="text/javascript" charset="utf-8">
				$.clipboardReady(function(){
					$.clipboard("' . $url . '");
					return false;
				}, { swfpath: "' . base_url() . 'static/flash/jquery.clipboard.swf"} );
			</script>';
		}
		
		if ($replies) 
		{
			$this->db->select('title, name, created, pid, snipurl');
			$this->db->where('replyto', $data['pid']);
			$this->db->order_by('id', 'desc');
			$this->db->limit(10);
			$query = $this->db->get('pastes');
			
			if ($query->num_rows() > 0) 
			{
				$n = 0;
				foreach ($query->result_array() as $row) 
				{
					$data['replies'][$n]['title'] = $row['title'];
					$data['replies'][$n]['name'] = $row['name'];
					$data['replies'][$n]['created'] = $row['created'];
					$data['replies'][$n]['pid'] = $row['pid'];
					$data['replies'][$n]['snipurl'] = $row['snipurl'];
					$n++;
				}
			}
			else
			{
				$replies = false;
			}
		}
		return $data;
	}
	
	function getLists($root = 'lists/', $seg = 2) 
	{
		$this->load->library('pagination');
		$amount = $this->config->item('per_page');
		
		if (!$this->uri->segment(2)) 
		{
			$page = 0;
		}
		else
		{
			$page = $this->uri->segment(2);
		}
		$this->db->where('private', 0);
		$this->db->order_by('created', 'desc');
		$query = $this->db->get('pastes', $amount, $page);
		$data['pastes'] = $query->result_array();
		$config['base_url'] = site_url($root);
		$config['total_rows'] = $this->countPastes();
		$config['per_page'] = $amount;
		$config['full_tag_open'] = '<div class="pages">';
		$config['full_tag_close'] = '</div>';
		$config['uri_segment'] = $seg;
		$this->pagination->initialize($config);
		$data['pages'] = $this->pagination->create_links();
		return $data;
	}
	
	function cron() 
	{
		$now = now();
		$this->db->where('toexpire', '1');
		$query = $this->db->get('pastes');
		foreach ($query->result_array() as $row) 
		{
			$stamp = $row['expire'];
			
			if ($now > $stamp) 
			{
				$this->db->where('id', $row['id']);
				$this->db->delete('pastes');
			}
		}
		return;
	}
}