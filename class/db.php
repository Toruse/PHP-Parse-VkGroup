<?php
/**
 * Класс для сохранения полученных данных в базу данных.
 */
class Base 
{
    /**
     * Хранит конфигурацию для работы с базой данных.
     * @var array 
     */
    private $config=NULL;
    
    /**
     * Контейнер для базы данных.
     * @var PDO 
     */
    private $db=NULL;
    
    /**
     * Конструктор.
     */
    public function __construct()
    {
        //Загружаем конфигурацию
        $this->config=include('config/db.php');
        //Подключаемся к базе данных.
        $this->db=new PDO('mysql:host='.$this->config['host'].';dbname='.$this->config['dbname'],$this->config['user'],$this->config['password']);
    }
    
    /**
     * Возвращает время последнего добавленного поста в базу данных.
     * @return integer
     */
    public function getMaxTimePost()
    {
        $row=$this->db->query("SELECT MAX(time_create) AS max FROM post")->fetch(PDO::FETCH_ASSOC);
        return $row['max'];
    }
    
    /**
     * Выполняет загрузку изображений на сервер.
     * @param array $listFoto - список изображений с параметрами
     */
    public static function loadFoto($listFoto)
    {
        foreach ($listFoto as $foto) 
        {
            if (!file_exists($foto['path']))
            {
                mkdir($foto['path'],0777,TRUE);
            }
            file_put_contents($foto['path'].'/'.$foto['file_mini'],file_get_contents($foto['img_mini']));
            file_put_contents($foto['path'].'/'.$foto['file_original'],file_get_contents($foto['img_original']));
        }
    }
    
    /**
     * Добавляет новые посты в базу данных.
     * @param array $sqlPost - список постов
     * @return integer - количество добавленных записей
     */
    public function insertPost($sqlPost)
    {
        $row=$this->db->query("SELECT id FROM post WHERE id IN ('".implode("','",array_keys($sqlPost))."')")->fetchAll(PDO::FETCH_ASSOC);
        if (is_array($row))
        {
            foreach ($row as $el)
            {
                if (isset($sqlPost[$el['id']]))
                {
                    unset($sqlPost[$el['id']]);
                }
            }
        }
        $sql="INSERT INTO post (id,user_id,description,time_create,date_create) VALUES ".implode(",",$sqlPost);
        $this->db->exec($sql);
        return count($sqlPost);
    }
    
    /**
     * Добавляет новых авторов в базу данных.
     * @param array $sqlAutor - список авторов
     * @return integet - количество добавленных записей
     */
    public function insertUser($sqlAutor)
    {
        $row=$this->db->query("SELECT id FROM user WHERE id IN ('".implode("','",array_keys($sqlAutor))."')")->fetchAll(PDO::FETCH_ASSOC);
        if (is_array($row))
        {
            foreach ($row as $el)
            {
                if (isset($sqlAutor[$el['id']]))
                {
                    unset($sqlAutor[$el['id']]);
                }
            }
        }
        $sql="INSERT INTO user (id,name,href) VALUES ".implode(",",$sqlAutor);
        $this->db->exec($sql); 
        return count($sqlAutor);
    }
    
    /**
     * Добавляет новые изображения в базу данных.
     * @param type $sqlFoto - список изображений
     * @return integer - количество добавленных записей
     */
    public function insertFoto($sqlFoto)
    {
        $row=$this->db->query("SELECT id FROM foto WHERE id IN ('".implode("','",array_keys($sqlFoto))."')")->fetchAll(PDO::FETCH_ASSOC);
        if (is_array($row))
        {
            foreach ($row as $el)
            {
                if (isset($sqlFoto[$el['id']]))
                {
                    unset($sqlFoto[$el['id']]);
                    unset($sqlFotoLoad[$el['id']]);
                }
            }
        }
        $sql="INSERT INTO foto (id,post_id,href_img_mini,href_img_original,ext,path,file_mini,file_original,href) VALUES ".implode(",",$sqlFoto);
        $this->db->exec($sql);
        return count($sqlFoto);
    }
}
?>