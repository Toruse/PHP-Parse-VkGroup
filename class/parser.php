<?php
/**
 * Класс для парсинга ресурса http://vk.com/hunterphotos.
 */
class Parser
{
    /**
     * Хранит конфигурацию парсера.
     * @var array 
     */
    private $config=NULL;
    
    /**
     * Хранит время последнего добавленного поста в базу данных.
     * @var integer 
     */
    private $maxTime=0;
    
    /**
     * Хранит время последнего добавленного поста полученного из ресурса.
     * @var integer 
     */
    private $lostTime=0;
    
    /**
     * Хранит результат парсинга.
     * @var array 
     */
    private $result=array();

    /**
     * Конструктор.
     */
    public function __construct() 
    {
        //Загружаем конфигурацию
        $this->config=include('config/parser.php');
    }

    /**
     * Получает первую страницу с данными из ресурса.
     * @return array - массив хранит данные полученные с ресурса и дополнительные данные о результате запроса
     */
    private function getWebPage()
    {
        $ch=curl_init($this->config['url']);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_ENCODING,"");
        curl_setopt($ch,CURLOPT_USERAGENT,$this->config['uagent']);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,60);
        curl_setopt($ch,CURLOPT_TIMEOUT,60);
        curl_setopt($ch,CURLOPT_MAXREDIRS,3);
        $cookie='remixlang=0';
        curl_setopt($ch,CURLOPT_COOKIE,$cookie);

        $content=curl_exec($ch);
        $err=curl_errno($ch);
        $errmsg=curl_error($ch);
        $header=curl_getinfo($ch);
        curl_close($ch);

        $header['errno']=$err;
        $header['errmsg']=$errmsg;
        $header['content']=$content;
        return $header;        
    }
    
    /**
     * Получает последующие страницы с ресурса.
     * @param string $postdata - передаваемые параметры
     * @return array - массив хранит данные полученные с ресурса и дополнительные данные о результате запроса
     */
    private function getPostWebPage($postdata)
    {
        $ch=curl_init($this->config['ajaxUrl']);
        curl_setopt($ch,CURLOPT_HTTPHEADER, array('X-Requested-With: XMLHttpRequest')); 
        curl_setopt($ch,CURLOPT_URL,$this->config['ajaxUrl']);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_USERAGENT,$this->config['uagent']);
        curl_setopt($ch,CURLOPT_TIMEOUT,60);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$postdata);
        $cookie='remixlang=0';
        curl_setopt($ch,CURLOPT_COOKIE,$cookie);

        $content=curl_exec($ch);
        $err=curl_errno($ch);
        $errmsg=curl_error($ch);
        $header=curl_getinfo($ch);
        curl_close($ch);

        $header['errno']=$err;
        $header['errmsg']=$errmsg;
        $header['content']=mb_convert_encoding($content,"utf-8","windows-1251");
        return $header;        
    }
    
    /**
     * Парсит первую страницу.
     */
    private function parseFirstPage()
    {
        //Получаем данные с ресурса
        $data=$this->getWebPage();
        //Получаем список постов
        $document=phpQuery::newDocument($data['content']);
        $posts=$document->find('div.post.all.own:not(.post_fixed)');
        //Обрабатываем посты
        foreach ($posts as $post) 
        {
            $pq=pq($post);
            if ($pq->find('table.published_by_wrap')->length()==0)
            {
                //Находим общие данные
                $code_str=$pq->attr('id');
                $code=preg_split("/[-_]+/",$code_str);
                $code[]=$code_str;
                $index=$code[1]."_".$code[2];
                $this->result[$index]['code']=$code;
                $author=$pq->find('a.author:first');
                $this->result[$index]['post_author']['href']=$author->attr('href');
                $this->result[$index]['post_author']['name']=$author->text();
                $this->result[$index]['description']=$pq->find('div.wall_post_text')->text();
                //Находим время создания поста
                $this->result[$index]['time']=$pq->find('span.rel_date.rel_date_needs_update')->attr('time');
                if ($this->result[$index]['time']=='')
                {
                    $this->result[$index]['time_view']=self::dateRuToEn($pq->find('span.rel_date:first')->text());
                    $this->result[$index]['time']=strtotime($this->result[$index]['time_view']);            
                }
                else
                {
                    $this->result[$index]['time_view']=date('Y-m-d H:m:s',$this->result[$index]['time']);
                }
                $this->lostTime=$this->result[$index]['time'];
                if ($this->maxTime>=$this->lostTime) 
                {
                    $this->lostTime=$this->maxTime;
                    unset($this->result[$index]);
                    break;
                }
                //Находим данные об авторе
                $author=$pq->find('a.wall_signed_by');
                if ($author->length()>0)
                {
                    $this->result[$index]['author']['id']=$author->attr('mention_id');
                    $this->result[$index]['author']['name']=$author->text();
                    $this->result[$index]['author']['href']=$author->attr('href');
                }
                //Находим данные об изображении
                $fotos=$pq->find("div.wall_text")->find('div.page_post_queue_wide')->find('a');
                if ($fotos->length()>1)
                {
                    foreach ($fotos as $foto)
                    {
                        $pathfile=$foto->find('img')->attr('src');
                        $ext=self::getExtension($pathfile);
                        $eventFoto=pq($foto)->attr('onclick');
                        $this->result[$index]['foto'][]=array(
                            'code'=>$this->getCodeImage($eventFoto),
                            'img_mini'=>$pathfile,
                            'img_original'=>$this->getOriginalPathImage($eventFoto).$ext,
                            'ext'=>$ext
                        );
                    }
                }
                elseif ($fotos->length()==1)
                {
                    $pathfile=$fotos->find('img')->attr('src');
                    $ext=self::getExtension($pathfile);
                    $eventFoto=$fotos->attr('onclick');
                    $this->result[$index]['foto'][]=array(
                        'code'=>$this->getCodeImage($eventFoto),
                        'img_mini'=>$pathfile,
                        'img_original'=>$this->getOriginalPathImage($eventFoto).'.'.$ext,
                        'ext'=>$ext
                    );
                }
            }
        }
    }
    
    /**
     * Парсит последующие страницы.
     */
    private function parseAJAXPage()
    {
        //Проходим по страница, оканчиваем работу когда найдём пустую страницу или уже пропарсеный пост
        $i=1;
        while ($this->maxTime<$this->lostTime)
        {
            //Получаем данные с ресурса
            $postdata='act=get_wall&al=1&fixed=18430&offset='.($i*10).'&owner_id=-71595791&type=own';
            $data=$this->getPostWebPage($postdata);
            $data=explode('<!>',$data['content']);
            if ($data[5]=='[]') break;
            $document=phpQuery::newDocument($data[5]);
            $posts=$document->find('div.post.all.own:not(.post_fixed)');
            //Обрабатываем посты
            foreach ($posts as $post) 
            {
                $pq=pq($post);
                if ($pq->find('table.published_by_wrap')->length()==0)
                {
                    //Находим общие данные
                    $code_str=$pq->attr('id');
                    $code=preg_split("/[-_]+/",$code_str);
                    $code[]=$code_str;
                    $index=$code[1]."_".$code[2];
                    $this->result[$index]['code']=$code;
                    $author=$pq->find('a.author:first');
                    $this->result[$index]['post_author']['href']=$author->attr('href');
                    $this->result[$index]['post_author']['name']=$author->text();
                    $this->result[$index]['description']=$pq->find('div.wall_post_text')->text();
                    //Находим время создания поста
                    $this->result[$index]['time_view']=self::dateRuToEn($pq->find('span.rel_date:first')->text());
                    $this->result[$index]['time']=strtotime($this->result[$index]['time_view']);
                    $this->lostTime=$this->result[$index]['time'];
                    //Находим данные об авторе
                    $author=$pq->find('a.wall_signed_by');
                    if ($author->length()>0)
                    {
                        $this->result[$index]['author']['id']=$author->attr('mention_id');
                        $this->result[$index]['author']['name']=$author->text();
                        $this->result[$index]['author']['href']=$author->attr('href');
                    }
                    //Находим данные об изображении
                    $fotos=$pq->find("div.wall_text")->find('div.page_post_queue_wide')->find('a');
                    if ($fotos->length()>1)
                    {
                        foreach ($fotos as $foto)
                        {
                            $pathfile=pq($foto)->find('img')->attr('src');
                            $ext=self::getExtension($pathfile);
                            $eventFoto=pq($foto)->attr('onclick');
                            $this->result[$index]['foto'][]=array(
                                'code'=>$this->getCodeImage($eventFoto),
                                'img_mini'=>$pathfile,
                                'img_original'=>$this->getOriginalPathImage($eventFoto).'.'.$ext,
                                'ext'=>$ext
                            );
                        }
                    }
                    elseif ($fotos->length()==1)
                    {
                        $pathfile=$fotos->find('img')->attr('src');
                        $ext=self::getExtension($pathfile);
                        $eventFoto=$fotos->attr('onclick');
                        $this->result[$index]['foto'][]=array(
                            'code'=>$this->getCodeImage($eventFoto),
                            'img_mini'=>$pathfile,
                            'img_original'=>$this->getOriginalPathImage($eventFoto).'.'.$ext,
                            'ext'=>$ext
                        );
                    }
                }
            }
            $i++;
        }            
    }
    
    /**
     * Ищет в строке путь к оригинальному изображению.
     * @param string $str
     * @return string
     */
    private function getOriginalPathImage($str)
    {
        preg_match("/{([^}]+)}*/i",$str,$found);
        $result=json_decode($found[0].'}',true);
        if (isset($result['temp']['z_']))
        {
            return $result['temp']['base'].$result['temp']['z_'][0];
        }
        elseif (isset($result['temp']['y_']))
        {
            return $result['temp']['base'].$result['temp']['y_'][0];    
        }
        elseif (isset($result['temp']['x_']))
        {
            return $result['temp']['base'].$result['temp']['x_'][0];    
        }
    }
    
    /**
     * Возвращает из строки код изображения.
     * @param string $str
     * @return string
     */
    private function getCodeImage($str)
    {
        preg_match("/showPhoto\('-(\w*)'/mi",$str,$found);
        return $found[1];
    }
    
    /**
     * Выполняет парсинг новых постов.
     * @return Parser
     */
    public function parserPost()
    {
        $this->parseFirstPage();
        $this->parseAJAXPage();
        return $this;
    }
    
    /**
     * Подготавливает данные для добавления их в базу.
     * @return array - массив содержит список авторов, постов, фотографий, которые нужно добавить в базу
     */
    public function preparation()
    {
        //Инициализируем данные
        $sqlPost=array();
        $sqlFoto=array();
        $sqlFotoLoad=array();
        $sqlAutor=array();

        $host=$this->config['post']['host'];
        $group=$this->config['post']['group'];

        //Перебираем результаты парсинга
        foreach ($this->result as $key=>$post) 
        {
            //Генерируем список авторов
            $author='';
            if (isset($post['author']))
            {
                $author=$post['author']['id'];
                $sqlAutor[$author]="('".$author."','".htmlspecialchars($post['author']['name'],ENT_QUOTES)."','".$host.$post['author']['href']."')";
            }
            else 
            {
                $author=str_replace('/','',$post['post_author']['href']);
                $sqlAutor[$author]="('".$author."','".htmlspecialchars($post['post_author']['name'],ENT_QUOTES)."','".$host.$post['post_author']['href']."')";
            }
            //Генерируем список постов
            $sqlPost[$key]="('".$key."','".$author."','".htmlspecialchars($post['description'],ENT_QUOTES)."','".$post['time']."','".$post['time_view']."')";
            //Генерируем список фотографий
            if (isset($post['foto']))
            {
                foreach ($post['foto'] as $foto)
                {
                    $pathFoto='file/'.date("Y")."/".date("m")."/".date("d");
                    $file_mini='mini'.basename($foto['img_mini']);
                    $file_original=basename($foto['img_original']);
                    $href=$host.'/'.$group.'?z=photo-'.$foto['code'].'%2Falbum-'.$post['code'][1].'_00%2Frev';
                    $sqlFoto[$foto['code']]="('".$foto['code']."','".$key."','".$foto['img_mini']."','".$foto['img_original']."',
                        '".$foto['ext']."','".$pathFoto."','".$file_mini."','".$file_original."','".$href."')";
                    $sqlFotoLoad[$foto['code']]=array(
                        'path'=>$pathFoto,
                        'img_mini'=>$foto['img_mini'],
                        'file_mini'=>$file_mini,
                        'img_original'=>$foto['img_original'],
                        'file_original'=>$file_original
                    );
                }
            }
        }
        return array(
            'sqlPost'=>$sqlPost,
            'sqlFoto'=>$sqlFoto,
            'sqlFotoLoad'=>$sqlFotoLoad,
            'sqlAutor'=>$sqlAutor 
        );
    }

    /**
     * Конвертирует русскую дату в стандартную.
     * @param string $date
     * @return string
     */
    private static function dateRuToEn($date)
    {
        $monthsRu=array(
            'минуту назад',
            'минуты назад',
            'минут назад',
            'час назад',
            'два часа назад',
            'три часа назад',
            'сегодня в',
            'вчера в',
        );

        $monthsEn=array(
            'минуту назад',
            'минуты назад',
            'минут назад',
            date("Y-m-d H:m:s",time()-3600),
            date("Y-m-d H:m:s",time()-7200),
            date("Y-m-d H:m:s",time()-10800),
            date("Y-m-d"),
            date("Y-m-d",time()-86400),
        );
        $date=str_replace($monthsRu,$monthsEn,$date);
        $monthsRu=array(
            'янв в',
            'фев в',
            'мар в',
            'апр в',
            'мая в',
            'июн в',
            'июл в',
            'авг в',
            'сен в',
            'окт в',
            'ноя в',
            'дек в'
        );
        foreach ($monthsRu as $key=>$value)
        {
            if (strpos($date,$value)>0)
            {
                $arrayValue=explode(" ",$date);
                $date=date('Y').'-'.($key+1).'-'.$arrayValue[0].' '.$arrayValue[3];
            }
        }
        return $date;
    }

    /**
     * Возвращает расширение файла.
     * @param string $filename - путь с именем файла
     * @return string - расширение файла
     */
    private static function getExtension($filename)
    {
       $path_info=pathinfo($filename);
       return $path_info['extension'];
    }

    /**
     * Устанавливает время последнего добавленного поста в базу данных.
     * @param integer $time
     */
    public function setMaxTime($time)
    {
        $this->maxTime=$time;
    }

    /**
     * Возвращает время последнего добавленного поста в базу данных.
     * @return integer
     */
    public function getMaxTime()
    {
        return $time;
    }
    
    /**
     * Возвращает результат парсинга.
     * @return array
     */
    public function getResult()
    {
        return $this->result;
    }
}
?>