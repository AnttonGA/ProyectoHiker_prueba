-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-09-2024 a las 16:34:47
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `peliculas`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `completed_routes`
--

CREATE TABLE `completed_routes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `route_id` int(11) DEFAULT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `completed_routes`
--

INSERT INTO `completed_routes` (`id`, `user_id`, `route_id`, `completed_at`) VALUES
(20, 6, 78, '2024-09-13 11:33:40'),
(23, 6, 79, '2024-09-14 08:30:44'),
(24, 6, 76, '2024-09-14 08:51:54'),
(26, 6, 77, '2024-09-14 08:53:33');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `list_routes`
--

CREATE TABLE `list_routes` (
  `id` int(11) NOT NULL,
  `list_id` int(11) DEFAULT NULL,
  `route_id` int(11) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `list_routes`
--

INSERT INTO `list_routes` (`id`, `list_id`, `route_id`, `added_at`) VALUES
(23, 11, 75, '2024-09-13 11:11:34'),
(24, 11, 78, '2024-09-13 11:12:20'),
(26, 1, 78, '2024-09-13 11:25:38'),
(27, 12, 76, '2024-09-13 14:19:36'),
(29, 13, 76, '2024-09-13 14:33:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `precalculated_recommendations`
--

CREATE TABLE `precalculated_recommendations` (
  `user_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `recommendation_score` float DEFAULT NULL,
  `last_updated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `route_popularity`
--

CREATE TABLE `route_popularity` (
  `route_id` int(11) NOT NULL,
  `view_count` int(11) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `favorite_count` int(11) DEFAULT 0,
  `complete_count` int(11) DEFAULT 0,
  `popularity_score` float DEFAULT NULL,
  `last_updated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `route_popularity`
--

INSERT INTO `route_popularity` (`route_id`, `view_count`, `download_count`, `favorite_count`, `complete_count`, `popularity_score`, `last_updated`) VALUES
(75, 0, 0, 1, 0, NULL, '2024-09-16 16:32:12'),
(76, 0, 0, 1, 1, NULL, '2024-09-14 10:55:47'),
(77, 0, 0, 1, 1, NULL, '2024-09-14 10:53:33'),
(78, 0, 0, 1, 1, NULL, '2024-09-13 13:33:40'),
(79, 0, 0, 0, 1, NULL, '2024-09-14 10:30:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `route_similarity`
--

CREATE TABLE `route_similarity` (
  `route_id1` int(11) NOT NULL,
  `route_id2` int(11) NOT NULL,
  `similarity_score` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutas`
--

CREATE TABLE `rutas` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `elevation` float DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `difficulty` enum('beginner','intermediate','advanced') DEFAULT NULL,
  `location` varchar(50) DEFAULT NULL,
  `length` float DEFAULT NULL,
  `startLat` decimal(10,8) DEFAULT NULL,
  `startLng` decimal(11,8) DEFAULT NULL,
  `startElevation` float DEFAULT NULL,
  `tipo_ruta` varchar(50) DEFAULT NULL,
  `ruta_carpeta` varchar(255) DEFAULT NULL,
  `pregunta1` text DEFAULT NULL,
  `pregunta2` text DEFAULT NULL,
  `pregunta3` text DEFAULT NULL,
  `pregunta4` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rutas`
--

INSERT INTO `rutas` (`id`, `usuario`, `filename`, `title`, `description`, `elevation`, `duration`, `difficulty`, `location`, `length`, `startLat`, `startLng`, `startElevation`, `tipo_ruta`, `ruta_carpeta`, `pregunta1`, `pregunta2`, `pregunta3`, `pregunta4`, `created_at`, `updated_at`) VALUES
(75, 'Antton', 'bisaurin-2-669m-desde-el-refugio-de-lizara-ruta-circular.gpx', 'Bisaurin circular', 'dffdgdfgfhgfhfghf', 1163.5, 7, 'intermediate', 'Western_Pyrenees', 11.4, 42.76400400, -0.63400800, 1514.65, 'hiking', 'bisaurin-circular-1725692590', 'fghfghfghfghfgh', 'fghfghfghfhgfhf', 'hfghfhfghfhfghfghfg', 'hfghfhfghfhfghgfhfg', '2024-09-07 07:03:10', '2024-09-07 07:03:10'),
(76, 'Antton', 'techo-de-navarra-mesa-de-los-tres-reyes2446-m-por-sendero-pa.gpx', 'Mesa de los tres reyes', 'adsdadsad', 1333.6, 8, 'intermediate', 'Western_Pyrenees', 17.24, 42.89800000, -0.79954500, 1345.05, 'hiking', 'mesa-de-los-tres-reyes-1725692699', 'asdad', 'asddasdasdasdasd', 'asddasdasdasdsa', 'dsaasdsadasdasdasd', '2024-09-07 07:04:59', '2024-09-07 07:04:59'),
(77, 'Antton', 'castillo-de-acher-desde-la-selva-de-oza.gpx', 'Castillo de Acher', 'dfdfsdfsdfsdf', 1286.1, 0, 'beginner', 'Western_Pyrenees', 13.24, 42.83333700, -0.71096800, 1128.27, 'ski_touring', 'castillo-de-acher-1725692813', 'dsfsdfsdfsdfs', 'sfsdfdsfsdfsdf', 'fdsfsdfsdfdsfsdsf', 'dsfsdfsdfsdfsdfsdf', '2024-09-07 07:06:53', '2024-09-07 07:06:53'),
(78, 'Antton', 'penaforca-y-lenito-alto-desde-oza-.gpx', 'Peña forca', 'feffdfsdfsdfsdf', 1313.2, 8, 'advanced', 'Western_Pyrenees', 11.67, 42.83426100, -0.71146700, 1068.55, 'ski_touring', 'peña-forca-1725692924', 'dsfsdfsdfsdfdsf', 'dsfsdfsdfsfdsfsd', 'fsdfsdfsdfsdfsdfs', 'sdffsdfsdfs', '2024-09-07 07:08:44', '2024-09-07 07:08:44'),
(79, 'Antton', 'senda-de-camille-de-lescun-camping-lauzart-al-refugio-de-lin.gpx', 'De Lescun a Linza', 'dfsdfdfdsfsdffgfsggfs', 1286.7, 24, 'intermediate', 'Western_Pyrenees', 17.17, 42.92780300, -0.64243200, 800.565, 'hiking', 'de-lescun-a-linza-1725693055', 'dfsfdsdfsdfsdfdsfsd', 'dfsdfsdfsdfsdfdsfsdf', 'dsfsdfdfsdfdsfdfs', 'sdfsdfsdfdffdsfssddf', '2024-09-07 07:10:55', '2024-09-07 07:10:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ruta_imagenes`
--

CREATE TABLE `ruta_imagenes` (
  `id` int(11) NOT NULL,
  `ruta_id` int(11) DEFAULT NULL,
  `imagen_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ruta_imagenes`
--

INSERT INTO `ruta_imagenes` (`id`, `ruta_id`, `imagen_path`) VALUES
(13, 75, 'rutas_st/bisaurin-circular-1725692590/25281394.400x300.jpg'),
(14, 75, 'rutas_st/bisaurin-circular-1725692590/25281389Master.jpg'),
(15, 76, 'rutas_st/mesa-de-los-tres-reyes-1725692699/112344522Master.jpg'),
(16, 76, 'rutas_st/mesa-de-los-tres-reyes-1725692699/112464822Master.jpg'),
(17, 77, 'rutas_st/castillo-de-acher-1725692813/12943360.400x300.jpg'),
(18, 77, 'rutas_st/castillo-de-acher-1725692813/12943355.400x300.jpg'),
(19, 78, 'rutas_st/peña-forca-1725692924/35628519Master.jpg'),
(20, 78, 'rutas_st/peña-forca-1725692924/35627545Master.jpg'),
(21, 79, 'rutas_st/de-lescun-a-linza-1725693055/12582598.400x300.jpg'),
(22, 79, 'rutas_st/de-lescun-a-linza-1725693055/12582514Master.jpg');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_favorites`
--

CREATE TABLE `user_favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `route_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `user_favorites`
--

INSERT INTO `user_favorites` (`id`, `user_id`, `route_id`, `created_at`) VALUES
(16, 6, 77, '2024-09-13 08:57:34'),
(24, 6, 78, '2024-09-13 11:31:45'),
(29, 6, 76, '2024-09-14 08:55:47'),
(35, 6, 75, '2024-09-16 14:28:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_interactions`
--

CREATE TABLE `user_interactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `route_id` int(11) DEFAULT NULL,
  `interaction_type` enum('view','download','favorite','complete') DEFAULT NULL,
  `interaction_date` datetime DEFAULT current_timestamp(),
  `view_duration` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_lists`
--

CREATE TABLE `user_lists` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `list_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `user_lists`
--

INSERT INTO `user_lists` (`id`, `user_id`, `list_name`, `created_at`) VALUES
(1, 6, 'prueba', '2024-09-13 08:42:34'),
(11, 6, 'antton', '2024-09-13 11:11:34'),
(12, 6, 'antton02', '2024-09-13 11:25:20'),
(13, 6, 'alpes franceses', '2024-09-13 14:33:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_preferences`
--

CREATE TABLE `user_preferences` (
  `user_id` int(11) NOT NULL,
  `experience_level` enum('beginner','intermediate','advanced') DEFAULT NULL,
  `preferred_activities` set('hiking','cycling','ski_touring','other') DEFAULT NULL,
  `preferred_location` varchar(100) DEFAULT NULL,
  `avg_preferred_distance` float DEFAULT NULL,
  `avg_preferred_duration` int(11) DEFAULT NULL,
  `avg_preferred_elevation` float DEFAULT NULL,
  `last_updated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellidos` varchar(255) NOT NULL,
  `mail` varchar(250) NOT NULL,
  `usuario` varchar(30) NOT NULL,
  `password` varchar(20) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `rol` enum('A','I','U','E') NOT NULL COMMENT 'A : Administrador U : usuario  I : invitado E: Editor',
  `estado` enum('A','P','B','D') NOT NULL COMMENT 'A : Activo P: Preinscrito D: Desactivado B: Borrado',
  `token` varchar(255) NOT NULL,
  `imagen` varchar(255) NOT NULL,
  `boletin` char(1) NOT NULL,
  `creado` bigint(20) NOT NULL COMMENT 'fecha creacion',
  `modificado` bigint(20) NOT NULL COMMENT 'fecha ultima modificacion'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellidos`, `mail`, `usuario`, `password`, `telefono`, `rol`, `estado`, `token`, `imagen`, `boletin`, `creado`, `modificado`) VALUES
(6, 'Antton', 'Gorrochategui', 'anttontton@gmail.com', 'Antton', '7205Wert', '653893353', 'U', 'A', '', 'imagen.jpg', 'S', 1725634421, 1725634421);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `completed_routes`
--
ALTER TABLE `completed_routes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_route_id` (`route_id`);

--
-- Indices de la tabla `list_routes`
--
ALTER TABLE `list_routes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_list_routes_user_list` (`list_id`),
  ADD KEY `idx_route_id` (`route_id`);

--
-- Indices de la tabla `precalculated_recommendations`
--
ALTER TABLE `precalculated_recommendations`
  ADD PRIMARY KEY (`user_id`,`route_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_route_id` (`route_id`);

--
-- Indices de la tabla `route_popularity`
--
ALTER TABLE `route_popularity`
  ADD PRIMARY KEY (`route_id`),
  ADD KEY `idx_route_id` (`route_id`);

--
-- Indices de la tabla `route_similarity`
--
ALTER TABLE `route_similarity`
  ADD PRIMARY KEY (`route_id1`,`route_id2`),
  ADD KEY `idx_route_id1` (`route_id1`),
  ADD KEY `idx_route_id2` (`route_id2`);

--
-- Indices de la tabla `rutas`
--
ALTER TABLE `rutas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_difficulty` (`difficulty`),
  ADD KEY `idx_location` (`location`),
  ADD KEY `idx_tipo_ruta` (`tipo_ruta`);

--
-- Indices de la tabla `ruta_imagenes`
--
ALTER TABLE `ruta_imagenes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ruta_id` (`ruta_id`);

--
-- Indices de la tabla `user_favorites`
--
ALTER TABLE `user_favorites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_route_id` (`route_id`);

--
-- Indices de la tabla `user_interactions`
--
ALTER TABLE `user_interactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_route_id` (`route_id`);

--
-- Indices de la tabla `user_lists`
--
ALTER TABLE `user_lists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_lists_user` (`user_id`);

--
-- Indices de la tabla `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `completed_routes`
--
ALTER TABLE `completed_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `list_routes`
--
ALTER TABLE `list_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de la tabla `rutas`
--
ALTER TABLE `rutas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT de la tabla `ruta_imagenes`
--
ALTER TABLE `ruta_imagenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `user_favorites`
--
ALTER TABLE `user_favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de la tabla `user_interactions`
--
ALTER TABLE `user_interactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_lists`
--
ALTER TABLE `user_lists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `completed_routes`
--
ALTER TABLE `completed_routes`
  ADD CONSTRAINT `fk_completed_routes_route` FOREIGN KEY (`route_id`) REFERENCES `rutas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_completed_routes_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `list_routes`
--
ALTER TABLE `list_routes`
  ADD CONSTRAINT `fk_list_routes_route` FOREIGN KEY (`route_id`) REFERENCES `rutas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_list_routes_user_list` FOREIGN KEY (`list_id`) REFERENCES `user_lists` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `precalculated_recommendations`
--
ALTER TABLE `precalculated_recommendations`
  ADD CONSTRAINT `fk_precalculated_recommendations_route` FOREIGN KEY (`route_id`) REFERENCES `rutas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_precalculated_recommendations_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `route_popularity`
--
ALTER TABLE `route_popularity`
  ADD CONSTRAINT `fk_route_popularity_route` FOREIGN KEY (`route_id`) REFERENCES `rutas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `route_similarity`
--
ALTER TABLE `route_similarity`
  ADD CONSTRAINT `fk_route_similarity_route1` FOREIGN KEY (`route_id1`) REFERENCES `rutas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_route_similarity_route2` FOREIGN KEY (`route_id2`) REFERENCES `rutas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ruta_imagenes`
--
ALTER TABLE `ruta_imagenes`
  ADD CONSTRAINT `fk_ruta_imagenes_ruta` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `user_favorites`
--
ALTER TABLE `user_favorites`
  ADD CONSTRAINT `fk_user_favorites_route` FOREIGN KEY (`route_id`) REFERENCES `rutas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `user_interactions`
--
ALTER TABLE `user_interactions`
  ADD CONSTRAINT `fk_user_interactions_route` FOREIGN KEY (`route_id`) REFERENCES `rutas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_interactions_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `user_lists`
--
ALTER TABLE `user_lists`
  ADD CONSTRAINT `fk_user_lists_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `fk_user_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
