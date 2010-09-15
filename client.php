<?php
/*
 *
 *Software Copyright License Agreement (BSD License)
 *
 *Copyright (c) 2010, Yahoo! Inc.
 *All rights reserved.
 *
 *Redistribution and use of this software in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 ** Redistributions of source code must retain the above
 *  copyright notice, this list of conditions and the
 *  following disclaimer.
 *
 ** Redistributions in binary form must reproduce the above
 *  copyright notice, this list of conditions and the
 *  following disclaimer in the documentation and/or other
 *  materials provided with the distribution.
 *
 ** Neither the name of Yahoo! Inc. nor the names of its
 *  contributors may be used to endorse or promote products
 *  derived from this software without specific prior
 *  written permission of Yahoo! Inc.
 *
 *THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
*/

define('USERNAME', '');
define('PASSWORD', '');
define('CONSUMER_KEY', '');
define('SECRET_KEY', '');

include_once 'jymengine.class.php';

$engine = new JYMEngine(CONSUMER_KEY, SECRET_KEY, USERNAME, PASSWORD);
$engine->debug = false;

if ($engine->debug) echo '> Fetching request token'. PHP_EOL;
if (!$engine->fetch_request_token()) die('Fetching request token failed');

if ($engine->debug) echo '> Fetching access token'. PHP_EOL;
if (!$engine->fetch_access_token()) die('Fetching access token failed');

if ($engine->debug) echo '> Signon as: '. USERNAME. PHP_EOL;
if (!$engine->signon('I am login from PHP code')) die('Signon failed');

$seq = -1;
while (true)
{
	$resp = $engine->fetch_long_notification($seq+1);
	if (isset($resp))
	{	
		if ($resp === false) 
		{		
			if ($engine->get_error() != -10)
			{
				if ($engine->debug) echo '> Fetching access token'. PHP_EOL;
				if (!$engine->fetch_access_token()) die('Fetching access token failed');				
				
				if ($engine->debug) echo '> Signon as: '. USERNAME. PHP_EOL;
				if (!$engine->signon(date('H:i:s'))) die('Signon failed');
				
				$seq = -1;
			}
			continue;							
		}
		
		
		foreach ($resp as $row)
		{
			foreach ($row as $key=>$val)
			{
				if ($val['sequence'] > $seq) $seq = intval($val['sequence']);
				
				/*
				 * do actions
				 */
				if ($key == 'buddyInfo') //contact list
				{
					if (!isset($val['contact'])) continue;
					
					if ($engine->debug) echo PHP_EOL. 'Contact list: '. PHP_EOL;
					foreach ($val['contact'] as $item)
					{
						if ($engine->debug) echo $item['sender']. PHP_EOL;
					}
					if ($engine->debug) echo '----------'. PHP_EOL;
				}
				
				else if ($key == 'message') //incoming message
				{
					if ($engine->debug) echo '+ Incoming message from: "'. $val['sender']. '" on "'. date('H:i:s', $val['timeStamp']). '"'. PHP_EOL;
					if ($engine->debug) echo '   '. $val['msg']. PHP_EOL;
					if ($engine->debug) echo '----------'. PHP_EOL;
					
					//reply
					$words = explode(' ', trim(strtolower($val['msg'])));
					if ($words[0] == 'help')
					{
						$out = 'This is Yahoo! Open API demo'. PHP_EOL;
						$out .= '  To get recent news from yahoo type: news'. PHP_EOL;
						$out .= '  To get recent entertainment news from yahoo type: omg'. PHP_EOL;						
						$out .= '  To change my/robot status type: status newstatus'. PHP_EOL;
					}
					else if ($words[0] == 'news')
					{
						if ($engine->debug) echo '> Retrieving news rss'. PHP_EOL;
						$rss = file_get_contents('http://rss.news.yahoo.com/rss/topstories');
												
						if (preg_match_all('|<title>(.*?)</title>|is', $rss, $m))
						{
							$out = 'Recent Yahoo News:'. PHP_EOL;
							for ($i=2; $i<7; $i++)
							{
								$out .= str_replace("\n", ' ', $m[1][$i]). PHP_EOL;
							}
						}
					}
					else if ($words[0] == 'omg')
					{
						if ($engine->debug) echo '> Retrieving OMG news rss'. PHP_EOL;
						$rss = file_get_contents('http://rss.omg.yahoo.com/latest/news/');
												
						if (preg_match_all('|<title>(.*?)</title>|is', $rss, $m))
						{
							$out = 'Recent OMG News:'. PHP_EOL;
							for ($i=2; $i<7; $i++)
							{
								$out .= str_replace(array('<![CDATA[', ']]>'), array('', ''), $m[1][$i]). PHP_EOL;
							}
						}
					}	
					else if ($words[0] == 'status')
					{
						$engine->change_presence(str_replace('status ', '', strtolower($val['msg'])));
						$out = 'My status is changed';
					}	
					else
					{
						$out = 'Please type: help';
					}
					
					//send message
					if ($engine->debug) echo '> Sending reply message '. PHP_EOL;
					if ($engine->debug) echo '    '. $out. PHP_EOL;	
					if ($engine->debug) echo '----------'. PHP_EOL;
					$engine->send_message($val['sender'], json_encode($out));
				}
				
				else if ($key == 'buddyAuthorize') //incoming contact request
				{
					if ($engine->debug) echo PHP_EOL. 'Accept buddy request from: '. $val['sender']. PHP_EOL;					
					if ($engine->debug) echo '----------'. PHP_EOL;	
					if (!$engine->response_contact($val['sender'], true, 'Welcome to my list'))
					{
						$engine->delete_contact($val['sender']);
						$engine->response_contact($val['sender'], true, 'Welcome to my list');
					}
				}
		}
		}
	}	
}

?>
