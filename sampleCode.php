<?php
  //Sample code. This class was writen by myself, apart from the login function. It is part of a proprietary framework developed by the company.
  //It proccesses API responses from both the custommer's CRM and Whatsapp to create an interface where the end user can login and create support tickets on the custommer's CRM. The front end is done in JS on Blip.

	class cliente extends common{
		
		public function __construct($gets) {
  			parent::__construct();

  			$this->gets = $gets;

  			$return = array();

  			$return['stt'] = false;
			
	        $this->json = json_decode(file_get_contents('php://input'), true);
	        	
	        $log = "(Início)";
			$log.= "\r\n\r\nBody:\r\n".json_encode($this->json, JSON_PRETTY_PRINT)."\r\n";
			
			if(isset($this->json['action'])){
				if(method_exists($this, $this->json['action'])){
					$metodo = $this->json['action'];			
					$return['stt'] = true;
					$return = $this->$metodo();		

				}else{

					$return['stt'] = false;
					$return['msg'] = "Método não encontrado";

				}
			}else{
				$return['stt'] = false;
				$return['msg'] = "Método não especificado";
			}

			$log.= "\r\nResponse:\r\n";
			$log.= json_encode($return, JSON_PRETTY_PRINT);
			$log.= "\r\n\r\n(Fim)\r\n-------\r\n";
			
			file_put_contents('logs/cliente/'.date("Y-m-d H").'.txt', $log, FILE_APPEND);
	    
			$this->respond_json($return);
	    }

		// necessita data de nascimento. Deprecada em 20/10/2025
	    public function login_cv_deprecado(){
	    	$pessoa = $this->curl_cv("/v1/cvbot/pessoa?documento=".$this->json['documento']."&data_nascimento=".$this->json['data_nascimento']);
			//$pessoa = $this->curl_cv("/cvio/reserva?documento=".$this->json['documento']."&situacao=3");
	    	$return['cliente']['stt'] = false;

			if($pessoa['code'] != 400) {
				$return['cliente']['id'] = $pessoa['idpessoa'];
	    		$return['cliente']['nome'] = $pessoa['nome'];
	    		$return['cliente']['stt'] = true;

		    	foreach($pessoa['unidades'] as $key => $value){
		    		if($value['situacao_reserva']['idsituacao'] == 3){
		    	
			    		$unidade = array();

			    		$unidade['idunidade'] 			= $value['idunidade'];
			    		$unidade['unidade'] 			= ltrim($value['nome'], "0");
			    		
			    		$unidade['idbloco'] 			= $value['bloco']['idbloco'];
			    		$unidade['bloco'] 				= $value['bloco']['nome'];

			    		$unidade['idempreendimento'] 	= $value['empreendimento']['idempreendimento'];
			    		$unidade['empreendimento'] 		= $value['empreendimento']['nome'];

			    		$return['cliente']['unidades'][] = $unidade;

		    		}
		    	}
		    }
		    return $return;
	    }

		public function login_cv(){
			$pessoa = $this->curl_cv("/cvio/reserva?documento=".$this->json['documento']."&situacao=3");
	    	$return['cliente']['stt'] = false;

			if($pessoa['http_code'] == 200) {
				foreach($pessoa as $key => $value){
					if(isset($value['unidade'])){
						if($return['cliente']['stt'] != true){
							$return['cliente']['id'] = $value['titular']['idpessoa_cv'];
							$return['cliente']['nome'] = $value['titular']['nome'];
							$return['cliente']['stt'] = true;
						}
						
						$unidade['idunidade'] 			= $value['unidade']['idunidade_cv'];
						$unidade['unidade'] 			= ltrim($value['unidade']['unidade'], "0");
						
						$unidade['idbloco'] 			= $value['unidade']['idbloco_cv'];
						$unidade['bloco'] 				= $value['unidade']['bloco'];

						$unidade['idempreendimento'] 	= $value['unidade']['idempreendimento_cv'];
						$unidade['empreendimento'] 		= $value['unidade']['empreendimento'];

						$return['cliente']['unidades'][] = $unidade;
					}
				}
		    }
		    return $return;
	    }

	    public function consulta_tamanho_arquivo(){

	    	$return['stt'] = false;
		    $return['tamanho'] = "Tamanho desconhecido (sem Content-Length)";
	    	$url = $this->json['url'];

	    	$limite_arquivo = 12000000;

	    	$headers = get_headers($url, 1);

		    if ($headers === false) {
		    	$return['tamanho'] = "Erro ao acessar a URL";
		        return $return;
		    }

		    // Verifica se existe o cabeçalho 'Content-Length'
		    if (isset($headers['Content-Length'])) {

		        // Pode haver múltiplos cabeçalhos, pega o último valor
		        if (is_array($headers['Content-Length'])) {
		            $return['tamanho'] = (int)end($headers['Content-Length']);	
		        }
		        else{
		        	$return['tamanho'] =  (int)$headers['Content-Length'];
		        }		        

		        if($return['tamanho'] > $limite_arquivo){
		        	$return['tamanho'] = "O arquivo deve ter menos de 12MB";
	            	$return['stt'] = true;
	            }
		    }
			return $return;
	    }

	    public function processa_atendimento(){

	    	$this->atendimento = $this->consulta_atendimento(true, true);

	    	if(!isset($this->atendimento['stt'])){

	    		return $this->adiciona_mensagem_atendimento();

	    	} else return $this->cadastra_atendimento();

	    }

	    public function consulta_atendimento_menu(){

	    	$return['stt'] = false;
	    	$menuatendimento = array();
	    	$msgatendimento = array();

	    	if($atendimentos = $this->consulta_atendimento(false)){
	    		if(!isset($atendimentos['stt'])){	    		
		    		foreach($atendimentos as $key => $value){
		    			$itemmenu['id'] 			= $value['idatendimento'];
		    			$itemmenu['label'] 			= $value['situacao'];
		    			$itemmenu['description'] 	= date("d/m/Y", strtotime($value['dataCad']))." - ".$value['subassunto'];
		    			$itemmenu['atendimento']	= $value;
		    			$menuatendimento[] = $itemmenu;

		    			//formata mensagem
		    			$datacad 					= (isset($value['dataCad']) ? date("d/m/Y", strtotime($value['dataCad'])): "");
		    				
		    			$msg['id'] 					= $value['idatendimento'];
		    			$msg['desc'] 				= $value['descricao'];
	    				$msg['msg'] 				= "Situação Atual: {$value['situacao']}\r\nAssunto: {$value['subassunto']}\r\nData de cadastro: {$datacad}";
						if($datavenc = (isset($value['dataVencimentoSubassunto'])? date("d/m/Y", strtotime($value['dataVencimentoSubassunto'])): null)){
	    					$msg['msg'] .=	($value['idsituacao'] != 4? "\r\nPrazo para resolução: {$datavenc}" : "");
	    						/*\r\n\nUnidade: {$value['unidades']}\r\nBloco: {$value['bloco']}\r\nEmpreendimento: {$value['empreendimento']}"*/
						}
	    				$msgatendimento[] 			= $msg;
		    		}
		    	}
		    	$itemmenu['id'] = 0;
		    	$itemmenu['label'] = "Voltar";
		    	$itemmenu['description'] = "Retorna ao menu anterior";

		    	$menuatendimento[] = $itemmenu;

		    	if(count($menuatendimento) == 1){
		    		$json['menu'] = $this->monta_menu_qr($menuatendimento, "", "", "Não foi encontrado nenhum atendimento.");

		    	}else{
					$body_menu = "Para te ajudar a entender o progresso do chamado, seu atendimento passará pelas etapas listadas abaixo:\n\n 1- Chamado Aberto\n2- Análise Técnica\n3- Em Contato com Cliente\n4- Em tratativa Astec\n5- Finalizado\n\nSobre qual atendimento gostaria de saber mais informações? Serão exibidos apenas os atendimentos que não foram finalizados.";
		    		$json['menu'] = $this->monta_menu($menuatendimento, "Atendimentos", "Selecionar", $body_menu);
		    	}	    	
		   	
		   		$json['json'] = $msgatendimento;

		    	return $json;

	    	}else {
	    		return $return;
	    	}

	    }

	    public function consulta_atendimento($filtra_situacao = true, $filtra_data = false){

	    	$return['stt'] = false;

	    	if(!$documento = $this->json['documento']){
	    		$documento = $this->json['cliente_cv']['documento'];
	    	}

		    if($atendimentos = $this->curl_cv("/v1/relacionamento/atendimentos/listar?documento=".$documento)){

		    	//situações no workflow de atendimento
		    	$situacoes_aceitas = array(1);

		    	//nome dos subassuntos para comparar com json
		    	if(!isset($this->json['idsubassunto'])){
		    		$idsubassuntos = $this->result("SELECT id FROM vic_rc_subassuntos WHERE vic_rc_assuntos_id = 14");
		    		$this->json['idsubassunto'] = [];
		    		foreach($idsubassuntos as $value){
		    			$this->json['idsubassunto'][] = $value['id'];
		    		}
		    	}
		    	
		    	$subassuntos_nome = $this->result("SELECT nome FROM vic_rc_subassuntos WHERE id IN (". (is_array($this->json['idsubassunto']) ? implode(", ", $this->json['idsubassunto']) : $this->json['idsubassunto']). ")");

	   			$atendimentos_bot = array();

	   			foreach($subassuntos_nome as $key => $value){
	   				foreach($atendimentos as $key2 => $value2){
						$dataCad = date("Y-m-d", strtotime($value2['dataCad']));
						if($filtra_data == false || $dataCad == date("Y-m-d")){
							//traz último (primeiro do json) atendimento encontrado que atende aos critérios
							if(mb_strtolower($value2['subassunto']) == mb_strtolower($value['nome']) && $value2['idsituacao'] != 4 &&(!$filtra_situacao || in_array($value2['idsituacao'], $situacoes_aceitas))){
								$atendimentos_bot[] = $value2;
							}
						}
	    			}
	    		}

	    		if(count($atendimentos_bot)){
	    			return $atendimentos_bot;
	    		}else return $return;
	    	}else return $return;
	    }

	    public function cadastra_atendimento(){

	    	if($this->json){
	    			
    			require("painel/class/class.cv.php");
	    		$this->cv = new cv();

    			$data = array(
				  				"cliente_cv" 			=> array(
				 					"idpessoa"			=> $this->json['cliente_cv']['idpessoa'],
				    				"documento" 		=> strval($this->json['cliente_cv']['documento'])
				  				),
				  				"titulo" 				=> $this->json['titulo'],
				  				"descricao" 			=> $this->json['descricao'],
				  				"telefone_atendimento" 	=> $this->json['telefone_atendimento'],
				  				"idempreendimento" 		=> $this->json['idempreendimento'],
				  				"idunidade" 			=> array($this->json['idunidade']),
				  				"idassunto" 			=> $this->json['idassunto'],
				  				"idsubassunto" 			=> $this->json['idsubassunto'],
				  				"cliente_visualiza" 	=> "N",
								);

    			$arrContextOptions=array(
				    "ssl"=>array(
				        "verify_peer"=>false,
				        "verify_peer_name"=>false,
				    ),
				);

    			if($this->json['arquivos']){
	    			foreach($this->json['arquivos'] as $key => $value){
		    			if($value['uri'] && $uri = @file_get_contents($value['uri'], false, stream_context_create($arrContextOptions))){

							//$type = pathinfo($value['uri'], PATHINFO_EXTENSION);
			    			$this->cv->base64 	= base64_encode($uri);
			    			$data["arquivos"][] = array("nome" => $value['nome'], "tipo" => $value['tipo'], "base64" => $this->cv->base64);
							//'data:image/' . $type . ';base64,' . 	
			    		}
		    		}
					
		    	}

				$this->registra_log("Base 64", $data);		
	    		if(!$this->cv->curl_cv($data, "/v1/relacionamento/atendimentos/cadastrar")){
    				$this->return['msg'][] = "Erro na requisição do CV";
    			}

				$this->return['response'] = $this->cv->response;
				$this->return['stt']  	= true;
				return $this->cv->response;

			}else{
				$this->return['msg'][] 	= "JSON inválido";
				$this->return['stt'] 	= false;
			}

			$this->respond_json($this->return);

	    }

	    public function adiciona_mensagem_atendimento(){

	    	if($this->json && $this->atendimento){

	    		require("painel/class/class.cv.php");
	    		$this->cv = new cv();

	    		$data = array(
	    						"idatendimento"		=> $this->atendimento[0]['idatendimento'],
				  				"mensagem" 			=> $this->json['descricao'],
				  				"cliente_visualiza" 	=> "N",
								);

    			$arrContextOptions=array(
				    "ssl"=>array(
				        "verify_peer"=>false,
				        "verify_peer_name"=>false,
				    ),
				);

    			if($this->json['arquivos']){
	    			foreach($this->json['arquivos'] as $key => $value){
		    			if($value['uri'] && $uri = @file_get_contents($value['uri'], false, stream_context_create($arrContextOptions))){

							//$type = pathinfo($value['uri'], PATHINFO_EXTENSION);
			    			$this->cv->base64 	= base64_encode($uri);
			    			$data["arquivos"][] = array("nome" => $value['nome'], "tipo" => $value['tipo'], "base64" => $this->cv->base64);
							//'data:image/' . $type . ';base64,' . 			
			    		}
		    		}
		    	}
				$this->registra_log("Base 64", $data);		
	    		if(!$this->cv->curl_cv($data, "/v1/relacionamento/atendimentos/mensagem-atendimento")){
    				$this->return['msg'][] = "Erro na requisição do CV";
    			}
    			$this->cv->response['id'] = $this->atendimento[0]['idatendimento'];
				$this->return['response'] = $this->cv->response;
				$this->return['stt']  	= true;
				$this->cv->response['protocolo_atendimento'] = date("ymd") . substr($this->cv->response['id'], 2);

				return $this->cv->response;

			}else{
				$this->return['msg'][] 	= "JSON inválido 2";
				$this->return['stt'] 	= false;
			}

			$this->respond_json($this->return);

	    }

	    public function login_cv_cnpj(){
	    	$pessoa = $this->curl_cv("/v1/cvbot/pessoa?documento=".$this->json['documento']);
	    	$return['cliente']['stt'] = false;

			if($pessoa['code'] != 400) {
				$return['cliente']['id'] = $pessoa['idpessoa'];
		    	$return['cliente']['nome'] = $pessoa['nome'];
	    		$return['cliente']['stt'] = true;

		    	foreach($pessoa['unidades'] as $key => $value){
		    	
		    		$unidade = array();

		    		$unidade['idunidade'] 			= $value['idunidade'];
		    		$unidade['unidade'] 			= ltrim($value['nome'], "0");
		    		
		    		$unidade['idbloco'] 			= $value['bloco']['idbloco'];
		    		$unidade['bloco'] 				= $value['bloco']['nome'];

		    		$unidade['idempreendimento'] 	= $value['empreendimento']['idempreendimento'];
		    		$unidade['empreendimento'] 		= $value['empreendimento']['nome'];

		    		$return['cliente']['unidades'][] = $unidade;
		    	
		    	}
		    }
	    	return $return;
	    }

	    public function curl_cv($endpoint){

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $this->cv_endpoint.$endpoint);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			    'email: '.$this->cv_email,
			    'token: '.$this->cv_token
			));
			

			$return = curl_exec($ch);
			$headers = curl_getinfo($ch);
			curl_close($ch);

			if($json = json_decode($return, true)){
				$json['http_code'] = $headers['http_code'];
				return $json;
			}else{
				return $headers;
			}

			
	    }

	    public function lista_subassuntos(){

    		$this->return['stt'] = false;
    		$this->return['msg'] = "id do assunto não encontrado";

    		$assunto = 14;

    		if(isset($this->json['idassunto'])){
    			$assunto = $this->json['idassunto'];
    		}

    		$titulo = "Assunto";
    		$body = "Sobre o que você deseja falar? 💁‍♀️";
    		$label = "Escolha abaixo";

    		if($subassuntos = $this->result("SELECT id, nome AS label, descricao AS description FROM vic_rc_subassuntos WHERE vic_rc_assuntos_id = ".$assunto." AND stt = 1 ORDER BY nome ASC")){

    			$botao_consultar['id'] 			= 0;
    			$botao_consultar['label'] 		= "Voltar";
    			$botao_consultar['description'] = "Retorna ao menu anterior";
    			$subassuntos[] = $botao_consultar;

    			if($return = $this->monta_menu($subassuntos, $titulo, $label, $body)){
    				$this->return['stt'] = true;
    				unset($this->return['msg']);
    				$this->return['subassuntos'] = $return;
    			}

    		}
    		$this->respond_json($this->return);

    	}

	    // Monta menu de botões com até 10 opções
	    // Recebe array de opções com id, label e description
	    public function monta_menu($data, $titulo = "", $cta = "", $body = "", $footer = "", $section_title = ""){
	        $json['recipient_type']                         = "individual";
	        $json['type']                                   = "interactive";
	        $json['interactive']['type']                    = "list";
	        
	        if($titulo != ""){
	            $json['interactive']['header']['type']          = "text";
	            $json['interactive']['header']['text']          = substr($titulo, 0, 60);
	        }

	        if($body != ""){
	            $json['interactive']['body']['text']            = substr($body, 0, 1024);
	        }

	        if($footer != ""){
	            $json['interactive']['footer']['text']          = substr($footer, 0, 60);
	        }

	        if($cta != ""){
	            $json['interactive']['action']['button']        = substr($cta, 0, 20);
	        }

	            
	        $section                                            = array();
	        if($section_title != ""){
	            $section['title']                               = substr($section_title, 0, 24);
	        }

	        $i = 0;
	        foreach($data as $key=>$value){
	            if($i == 10){
	                break;
	            }
	            $section['rows'][]  = array("id" => $value['id'], "title" => substr($value['label'], 0, 24), "description" => substr($value['description'], 0, 72));
	            $i++;
	        }

	        $json['interactive']['action']['sections'][] = $section;

	        return $json;
    	}

    	public function monta_menu_qr($data, $titulo = "", $cta = "", $body = "", $footer = "", $section_title = ""){
	        $json['recipient_type']                         = "individual";
	        $json['type']                                   = "interactive";
	        $json['interactive']['type']                    = "button";
	        
	        if($titulo != ""){
	            $json['interactive']['header']['type']          = "text";
	            $json['interactive']['header']['text']          = substr($titulo, 0, 60);
	        }

	        if($body != ""){
	            $json['interactive']['body']['text']            = substr($body, 0, 1024);
	        }

	        if($footer != ""){
	            $json['interactive']['footer']['text']          = substr($footer, 0, 60);
	        }
	            
	        $section                                            = array();
	        if($section_title != ""){
	            $section['title']                               = substr($section_title, 0, 24);
	        }

	        $i = 0;
	        foreach($data as $key=>$value){
	            if($i == 2){
	                break;
	            }
	            $reply['type']	= "reply";
	            $reply['reply'] = array("id" => $value['id'], "title" => substr($value['label'], 0, 20));
	            $buttons[] = $reply;
	            $i++;
	        }

	        $json['interactive']['action']['buttons'] = $buttons;

	        return $json;
    	}

	    private function registra_log($evento, $data){
  			
  			$log = "\r\n".date("d/m/Y H:i")." ".$evento."\r\n";
  			$log.= json_encode($data, JSON_PRETTY_PRINT)."\r\n";
  			$log.= "---------\r\n";

  			$file_name = "logs/cv/atendimento/".date("Y-m-d H").".txt";

  			file_put_contents($file_name, $log, FILE_APPEND);

  		}
	}
?>
