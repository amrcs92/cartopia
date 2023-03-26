-- phpMyAdmin SQL Dump
-- version 4.7.9
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 12, 2018 at 12:26 PM
-- Server version: 5.7.21
-- PHP Version: 5.6.35

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `shopping_cart`
--

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shipping_id` int(11) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shipping_id` (`shipping_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `shipping_id`, `total_cost`, `created_at`) VALUES
(1, 1, '51.08', '2018-08-11 06:53:30');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `price` double(10,2) NOT NULL,
  `sku` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `sku`, `created_at`, `updated_at`) VALUES
(1, 'Apple', 'Delicious and crunchy, apple fruit is one of the most popular and favorite fruits among the health conscious, fitness lovers who firmly believe in the concept of \"health is wealth.\" This wonderful fruit indeed packed with rich phytonutrients that in the real sense indispensable for optimal health and wellness. Certain antioxidants in apples have health promoting and disease prevention properties, and thereby, truly justifying the adage, \"an apple a day keeps the doctor away.\"', 0.30, 63310304118428, '2018-08-02 19:05:15', '2018-08-02 21:06:05'),
(2, 'Beer', 'Heineken Lager Beer, or simply Heineken is a pale lager beer with 5% alcohol by volume produced by the Dutch brewing company Heineken International. Heineken is well known for its signature green bottle and red star.', 2.00, 73310304118423, '2018-08-03 01:06:07', '2018-08-03 01:06:07'),
(3, 'Water', 'Proudly made in Buxton in the heart of the Peak District, Buxton Water is a natural mineral water. Find out about us and get Naturally Pumped Up. Buxton® Natural Mineral Water is sourced in Buxton town, Derbyshire at St Ann\'s Spring, in the heart of the beautiful Peak District.', 1.00, 93312304138923, '2018-08-03 05:12:11', '2018-08-03 05:12:11'),
(4, 'Cheese Triangle', 'Arla Triangles Cheese is made from 100-percent natural ingredients.It offers a great cream taste and texture. Arla Triangles Cheese is also versatile and can be used for many different dishes and cakes.', 3.74, 47729135118723, '2018-08-02 23:21:39', '2018-08-02 23:21:39');

-- --------------------------------------------------------

--
-- Table structure for table `products_order`
--

DROP TABLE IF EXISTS `products_order`;
CREATE TABLE IF NOT EXISTS `products_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `name`, `product_id`, `created_at`, `updated_at`) VALUES
(5, 'images/apple.jpg', 1, '2018-08-11 06:05:00', '2018-08-11 06:05:00'),
(6, 'images/heineken_beer.jpg', 2, '2018-08-11 06:06:20', '2018-08-11 06:06:20'),
(7, 'images/water_bottle.jpg', 3, '2018-08-11 07:08:00', '2018-08-11 07:08:00'),
(8, 'images/cheese-traingles.jpg', 4, '2018-08-11 09:06:00', '2018-08-11 09:06:00');

-- --------------------------------------------------------

--
-- Table structure for table `shipping`
--

DROP TABLE IF EXISTS `shipping`;
CREATE TABLE IF NOT EXISTS `shipping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `method` varchar(50) NOT NULL,
  `fees` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `shipping`
--

INSERT INTO `shipping` (`id`, `method`, `fees`) VALUES
(1, 'Pickup', 0),
(2, 'UPS', 5);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `phone` varchar(100) NOT NULL,
  `address` varchar(200) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `balance` decimal(10,2) NOT NULL,
  `user_role` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `phone`, `address`, `email`, `password`, `balance`, `user_role`) VALUES
(1, 'amrcs92', '01092237499', '14 may street - smouha - alexandria', 'amrcs1992@gmail.com', '$6$rounds=5000$usesomesillystri$5WxXV00Jv1lKssvR375aHSbVfBNbxuKpQx0oQSArCRfoC4IDPBd55jdlRyNa/zsrYE6EJKIQd6sNKKxhyHOne0', '1.84', 0),
(2, 'ahmed', '01298984394', 'mansheya - bahary - alexandria', 'ahmedmohamed@gmail.com', '$6$rounds=5000$usesomesillystri$5WxXV00Jv1lKssvR375aHSbVfBNbxuKpQx0oQSArCRfoC4IDPBd55jdlRyNa/zsrYE6EJKIQd6sNKKxhyHOne0', '100.00', 0),
(3, 'mahmoud', '01290239320', 'kheder el tony -nasr city - cairo', 'mahoud@gmail.com', '$6$rounds=5000$usesomesillystri$5WxXV00Jv1lKssvR375aHSbVfBNbxuKpQx0oQSArCRfoC4IDPBd55jdlRyNa/zsrYE6EJKIQd6sNKKxhyHOne0', '100.00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_rating`
--

DROP TABLE IF EXISTS `user_rating`;
CREATE TABLE IF NOT EXISTS `user_rating` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rate` int(11) NOT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `user_rating`
--

INSERT INTO `user_rating` (`id`, `user_id`, `product_id`, `rate`, `created_at`) VALUES
(1, 1, 3, 2, '2018-08-12 08:02:03'),
(2, 2, 3, 2, '2018-08-12 01:05:04'),
(11, 1, 4, 5, '2018-08-12 23:07:42'),
(13, 2, 2, 5, '2018-08-12 04:06:28'),
(14, 1, 2, 3, '2018-08-12 23:51:37');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`shipping_id`) REFERENCES `shipping` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `products_order`
--
ALTER TABLE `products_order`
  ADD CONSTRAINT `products_order_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `products_order_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_rating`
--
ALTER TABLE `user_rating`
  ADD CONSTRAINT `user_rating_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_rating_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
