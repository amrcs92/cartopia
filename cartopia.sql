-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 27, 2023 at 12:50 AM
-- Server version: 10.4.24-MariaDB
-- PHP Version: 8.1.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cartopia`
--

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `shipping_id` int(11) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `shipping_id`, `total_cost`, `created_at`) VALUES
(1, 2, '12.74', '2018-08-26 12:42:56'),
(2, 1, '4.00', '2018-08-27 07:36:03'),
(3, 2, '12.04', '2018-08-28 02:45:10'),
(4, 1, '6.00', '2018-08-29 12:09:26'),
(5, 2, '11.00', '2018-08-29 12:26:31');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `price` double(10,2) NOT NULL,
  `sku` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

CREATE TABLE `products_order` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `products_order`
--

INSERT INTO `products_order` (`id`, `order_id`, `product_id`, `quantity`) VALUES
(1, 1, 3, 2),
(2, 1, 4, 1),
(3, 1, 2, 1),
(4, 2, 3, 4),
(5, 3, 4, 1),
(6, 3, 3, 1),
(7, 3, 2, 1),
(8, 3, 1, 1),
(9, 4, 3, 6),
(10, 5, 3, 6);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

CREATE TABLE `shipping` (
  `id` int(11) NOT NULL,
  `method` varchar(50) NOT NULL,
  `fees` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `phone` varchar(100) NOT NULL,
  `address` varchar(200) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `balance` decimal(10,2) NOT NULL,
  `user_role` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `phone`, `address`, `email`, `password`, `balance`, `user_role`) VALUES
(1, 'amrcs92', '01092237499', '14 may street - smouha - alexandria', 'amrcs1992@gmail.com', '$6$rounds=5000$usesomesillystri$5WxXV00Jv1lKssvR375aHSbVfBNbxuKpQx0oQSArCRfoC4IDPBd55jdlRyNa/zsrYE6EJKIQd6sNKKxhyHOne0', '14.40', 0),
(2, 'ahmed', '01298984394', 'mansheya - bahary - alexandria', 'ahmedmohamed@gmail.com', '$6$rounds=5000$usesomesillystri$5WxXV00Jv1lKssvR375aHSbVfBNbxuKpQx0oQSArCRfoC4IDPBd55jdlRyNa/zsrYE6EJKIQd6sNKKxhyHOne0', '87.26', 0),
(3, 'mahmoud', '01290239320', 'kheder el tony -nasr city - cairo', 'mahoud@gmail.com', '$6$rounds=5000$usesomesillystri$5WxXV00Jv1lKssvR375aHSbVfBNbxuKpQx0oQSArCRfoC4IDPBd55jdlRyNa/zsrYE6EJKIQd6sNKKxhyHOne0', '100.00', 0),
(4, 'shahin89', '01394893534', 'California - USA', 'shahin89@gmail.com', '$6$rounds=5000$usesomesillystri$5WxXV00Jv1lKssvR375aHSbVfBNbxuKpQx0oQSArCRfoC4IDPBd55jdlRyNa/zsrYE6EJKIQd6sNKKxhyHOne0', '100.00', 0),
(5, 'ramzy', '0129843985', 'mansheya - alexandria', 'ramzy@gmail.com', '$6$rounds=5000$usesomesillystri$5WxXV00Jv1lKssvR375aHSbVfBNbxuKpQx0oQSArCRfoC4IDPBd55jdlRyNa/zsrYE6EJKIQd6sNKKxhyHOne0', '100.00', 0),
(6, 'son-goku', '0122345678909', 'vegeta planet - dragon ball', 'kamehameha@gmail.com', '$6$rounds=5000$usesomesillystri$5WxXV00Jv1lKssvR375aHSbVfBNbxuKpQx0oQSArCRfoC4IDPBd55jdlRyNa/zsrYE6EJKIQd6sNKKxhyHOne0', '100.00', 0),
(7, 'ahmedhussien', '019278459379', 'tanta - 3ataba - egypt', 'ahmedhussien@gmail.com', '$6$rounds=5000$usesomesillystri$5WxXV00Jv1lKssvR375aHSbVfBNbxuKpQx0oQSArCRfoC4IDPBd55jdlRyNa/zsrYE6EJKIQd6sNKKxhyHOne0', '87.96', 0),
(8, 'yassin', '01234456789', 'bakous - alexandria - egypt', 'yassin@gmail.com', '$6$rounds=5000$usesomesillystri$5WxXV00Jv1lKssvR375aHSbVfBNbxuKpQx0oQSArCRfoC4IDPBd55jdlRyNa/zsrYE6EJKIQd6sNKKxhyHOne0', '83.00', 0),
(9, 'amrcs92', '01092237499', '453 south spring street', 'amrcs1992@gmail.com', '$6$rounds=5000$usesomesillystri$hoaIZGYQkyfevDZUSlLGcwiMJ5PQrdFyjlQZZNJOgVU0urVnuk6vX1CVkCLKy5PsrloQ8YFrK3Q.qu0I460TH.', '100.00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_rating`
--

CREATE TABLE `user_rating` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rate` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `user_rating`
--

INSERT INTO `user_rating` (`id`, `user_id`, `product_id`, `rate`, `created_at`) VALUES
(1, 2, 2, 5, '2018-08-26 12:44:34'),
(2, 1, 2, 4, '2018-08-26 12:45:08'),
(3, 6, 2, 3, '2018-08-26 12:46:23'),
(4, 7, 3, 5, '2018-08-28 02:30:40'),
(5, 8, 4, 5, '2018-08-29 12:26:51'),
(6, 5, 1, 3, '2023-03-26 21:03:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shipping_id` (`shipping_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products_order`
--
ALTER TABLE `products_order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `shipping`
--
ALTER TABLE `shipping`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_rating`
--
ALTER TABLE `user_rating`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products_order`
--
ALTER TABLE `products_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `shipping`
--
ALTER TABLE `shipping`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_rating`
--
ALTER TABLE `user_rating`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
