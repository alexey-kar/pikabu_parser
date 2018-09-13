<?
class CPikabuParser
{
	private $m_dbSettings  = [];
	private $m_logFilename = '.PDOErrors.txt';
	private $m_debug       = false;
	
	
	public function __construct($arSettings = null)
	{
		$this->m_dbSettings['host']   = 'localhost';
		$this->m_dbSettings['dbname'] = '';
		$this->m_dbSettings['user']   = 'root';
		$this->m_dbSettings['pass']   = '';
		
		
		if ($arSettings != null) {
			if (isset($arSettings['host'])) {
				$this->m_dbSettings['host'] = $arSettings['host'];
			}
			
			if (isset($arSettings['dbname'])) {
				$this->m_dbSettings['dbname'] = $arSettings['dbname'];
			}
			
			if (isset($arSettings['user'])) {
				$this->m_dbSettings['user'] = $arSettings['user'];
			}
			
			if (isset($arSettings['pass'])) {
				$this->m_dbSettings['pass'] = $arSettings['pass'];
			}
			
			
			
			if (isset($arSettings['log_filename'])) {
				$this->m_logFilename = $arSettings['log_filename'];
			}
			
			if (isset($arSettings['debug'])) {
				$this->m_debug = $arSettings['debug'];
			}
		}
	}
	
	public function updateFrom($url)
	{
		$html_page = file_get_contents($url);
		$html_utf8 = mb_convert_encoding($html_page, 'utf-8', 'windows-1251');


		$arArticles = [];
		
		$doc = new DomDocument();
		@$doc->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' .  $html_utf8);

		
		foreach ($doc->childNodes as $item) {
			if ($item->nodeType == XML_PI_NODE) {
				$doc->removeChild($item); // remove hack
			}
			
			$doc->encoding = 'UTF-8'; // insert proper

			
			$articles = $doc->getElementsByTagName('article');
			
			foreach ($articles as $article) {
				if ($article->getAttribute('class') === 'story') { // article blocks
				
					if ($article->hasAttribute('data-rating') === false) { // ad article
						continue;
					}
					
					$arArticleItem = [];
					$arArticleItem['id_from_site'] = intval($article->getAttribute('data-story-id'));
					
					
					// get article time
					
					$links = $article->getElementsByTagName('a');

					foreach ($links as $link) {
						if ($link->getAttribute('class') === 'story__title-link') { // search story title
							$childs = $link->getElementsByTagName('*');

							foreach ($childs as $child) {
								$link->removeChild($child);
							}

							$arArticleItem['title'] = trim($link->nodeValue);
						}
					}
					
					
					
					// get article date publish
					
					$times = $article->getElementsByTagName('time');

					foreach ($times as $time) {
						if (strpos($time->getAttribute('class'), 'story__datetime') !== false) { // search story date publish
							$childs = $time->getElementsByTagName('*');

							foreach ($childs as $child) {
								$time->removeChild($child);
							}

							
							// 2018-09-13T16:15:00+03:00 - format
							$d = DateTime::createFromFormat('Y-m-d\TH:i:sP', trim($time->getAttribute('datetime')));
							
							if ($d) {
								$arArticleItem['date_publish'] = $d->format('Y-m-d h:i:s');
							} else {
								// log error
							}
						}
					}
					
					
					$arArticles[ $arArticleItem['id_from_site'] ] = $arArticleItem;
				}
			}
		}
		
		$this->saveInDb($arArticles);
	}
	
	private function saveInDb($arArticles)
	{
		if ($this->m_debug) {
			echo '<br>Parsed ('.count($arArticles).'):<pre>', print_R($arArticles), '</pre>';
		}
		
		
		try {
			$pdo = new PDO("mysql:host=".$this->m_dbSettings['host'].";dbname=".$this->m_dbSettings['dbname'], 
				$this->m_dbSettings['user'], $this->m_dbSettings['pass']); 
				
			$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );  

			
			
			if (count($arArticles)) { // remove exists rows
				$selectIds = [];
				
				foreach ($arArticles as $article) {
					$selectIds[] = $article['id_from_site'];
				}
				
				$stmt = $pdo->prepare('SELECT id_from_site FROM articles_from_sites WHERE id_from_site IN ('.implode(', ', $selectIds).');');
				$stmt->execute();
				
				while ($id_from_site = $stmt->fetch(PDO::FETCH_COLUMN)) {
					unset($arArticles[ $id_from_site ]);
				}
			}
			
			
			
			// insert new rows
			$pdo->beginTransaction();
			
			$stmt = $pdo->prepare('INSERT INTO articles_from_sites (title, date_publish, id_from_site) VALUES(:title, :date_publish, :id_from_site);');
			foreach ($arArticles as $article) {
				$stmt->bindValue(':title',        $article['title']);
				$stmt->bindValue(':date_publish', $article['date_publish']);
				$stmt->bindValue(':id_from_site', $article['id_from_site'], PDO::PARAM_INT);
				
				$stmt->execute();
			}
			
			$pdo->commit();
			
			
			if ($this->m_debug) {
				echo '<br>Inserted ('.count($arArticles).'):<pre>', print_R($arArticles), '</pre>';
			}
		} catch(PDOException $e) {
			//echo $e->getMessage();
			
			if (!empty($this->m_logFilename)) {
				file_put_contents($this->m_logFilename, $e->getMessage(), FILE_APPEND);
			}
		}
	}
}
?>