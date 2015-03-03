<?php

class DpdParcelShopFinder
{
	/**
	 * Path to ParcelShopFinder webservice wsdl.
	 */
	CONST WEBSERVICE_PARCELSHOP = 'ParcelShopFinderService/V3_0/?wsdl';
	
	public $url;
	public $login;
	
	public $results = array();
	
	public function __construct(DpdLogin $login, $long, $lat, $url = 'https://public-ws-stage.dpd.com/services/')	
	{
		$this->login = $login;
		$this->url = $this->getWebserviceUrl($url);
		
		$this->search($long, $lat);
	}
	
	public function search($long, $lat)
	{
		$stop = false;
		while(!$stop)
		{
			try {
				$client = new SoapClient($this->url);
				
				$soapHeader = $this->login->getSoapHeader();
				$client->__setSoapHeaders($soapHeader);

				$result = $client->findParcelShopsByGeoData(array(
					'longitude' => $long
					,'latitude' => $lat
					,'limit' =>'10'
					,'consigneePickupAllowed' => 'true'
					)
				);
			} 
			catch (SoapFault $soapE) 
			{
				switch($soapE->getCode())
				{
					case 'soap:Server':
						$splitMessage = explode(':', $soapE->getMessage());
						switch($splitMessage[0])
						{
							case 'cvc-complex-type.2.4.a':
								$newMessage = 'One of the mandatory fields is missing.';
								break;
							case 'cvc-minLength-valid':
								$newMessage = 'One of the values you provided is not long enough.';
								break;
							case 'cvc-maxLength-valid':
								$newMessage = 'One of the values you provided is too long.';
								break;
							case 'Fault occured':
								if($soapE->detail && $soapE->detail->authenticationFault)
								{
									switch($soapE->detail->authenticationFault->errorCode)
									{
										case 'LOGIN_5':
											$this->login->refresh();
											continue 4;
											break;
										case 'LOGIN_6':
											$this->login->refresh();
											continue 4;
											break;
										default:
									}
								}
								else
									$newMessage = 'Something went wrong, please use the Exception trace to find out';
								break;
							default:
								$newMessage = $soapE->getMessage();
								break;
						}
						break;
					case 'soap:Client':
						switch($soapE->getMessage())
						{
							case 'Error reading XMLStreamReader.':
								$newMessage = 'It looks like their is a typo in the xml call.';
								break;
							default:
								$newMessage = $soapE->getMessage();
								break;
						}
						break;
					default:
						$newMessage = $soapE->getMessage();
						break;
				}
				throw new Exception($newMessage, $soapE->getCode(), $soapE);
			} 
			catch (Exception $e) 
			{
				throw new Exception('Something went wrong with the connection to the DPD server', $e->getCode(), $e);
			}
			$stop = true;
		}

		foreach($result->parcelShop as $parcelShop)
		{
			$this->results[$parcelShop->parcelShopId] = $parcelShop;
		}
	}
	
	/**
	* Add trailing slash to url if not exists.
	*
	* @param $url
	* @return mixed|string
	*/
	protected function getWebserviceUrl($url)
	{
			if (substr($url, -1) != '/') {
					$url = $url . '/';
			}

			return $url . self::WEBSERVICE_PARCELSHOP;
	}
}