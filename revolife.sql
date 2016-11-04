SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `revolife`
--

-- --------------------------------------------------------

--
-- Структура таблицы `foto`
--

CREATE TABLE IF NOT EXISTS `foto` (
  `id` varchar(30) NOT NULL,
  `post_id` varchar(30) NOT NULL,
  `href_img_mini` varchar(500) NOT NULL,
  `href_img_original` varchar(500) NOT NULL,
  `path` varchar(255) NOT NULL,
  `file_mini` varchar(255) NOT NULL,
  `file_original` varchar(255) NOT NULL,
  `ext` varchar(5) NOT NULL,
  `href` varchar(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `post`
--

CREATE TABLE IF NOT EXISTS `post` (
  `id` varchar(30) NOT NULL,
  `user_id` varchar(30) NOT NULL,
  `description` text NOT NULL,
  `time_create` int(10) unsigned NOT NULL,
  `date_create` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `id` varchar(30) NOT NULL,
  `name` varchar(120) NOT NULL,
  `href` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
