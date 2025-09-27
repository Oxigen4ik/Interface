-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3306
-- Время создания: Сен 27 2025 г., 14:17
-- Версия сервера: 10.6.22-MariaDB-0ubuntu0.22.04.1
-- Версия PHP: 8.1.2-1ubuntu2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `project_Shchegolkov`
--

DELIMITER $$
--
-- Процедуры
--
CREATE DEFINER=`Shchegolkov`@`%` PROCEDURE `AddAdmin` (IN `p_username` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN `p_plain_password` VARCHAR(255))  BEGIN
    DECLARE hashed_password VARCHAR(255);
    
    SET hashed_password = SHA2(p_plain_password, 256);
    
    IF EXISTS (SELECT 1 FROM admins WHERE username COLLATE utf8mb4_unicode_ci = p_username COLLATE utf8mb4_unicode_ci) THEN
        SELECT 'Ошибка: Администратор с таким логином уже существует.' AS message;
    ELSE
        INSERT INTO admins (username, password, failed_attempts, is_locked, unlock_time)
        VALUES (p_username, hashed_password, 0, 0, NULL);
        
        SELECT 'Администратор успешно добавлен.' AS message;
    END IF;
END$$

CREATE DEFINER=`Shchegolkov`@`%` PROCEDURE `AddNewCustomer` (IN `p_Name` VARCHAR(15), IN `p_Phone` VARCHAR(20))  BEGIN
    -- Проверка на существование клиента с таким же телефоном
    IF NOT EXISTS (SELECT 1 FROM Customer WHERE Phone = p_Phone) THEN
        INSERT INTO Customer (Name, Phone)
        VALUES (p_Name, p_Phone);
        SELECT 'Клиент успешно добавлен' AS Message;
    ELSE
        SELECT 'Клиент с таким номером телефона уже существует' AS Message;
    END IF;
END$$

CREATE DEFINER=`Shchegolkov`@`%` PROCEDURE `DeleteAdmin` (IN `p_username` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)  BEGIN
    DECLARE row_count INT DEFAULT 0;
    
    DELETE FROM admins WHERE username COLLATE utf8mb4_unicode_ci = p_username COLLATE utf8mb4_unicode_ci;
    SET row_count = ROW_COUNT();
    
    IF row_count > 0 THEN
        SELECT CONCAT('Администратор "', p_username, '" успешно удален.') AS message;
    ELSE
        SELECT CONCAT('Ошибка: Администратор "', p_username, '" не найден.') AS message;
    END IF;
END$$

CREATE DEFINER=`Shchegolkov`@`%` PROCEDURE `GetOrderDetails` (IN `p_OrderID` INT)  BEGIN
    SELECT 
        o.ID AS OrderID,
        c.Name AS CustomerName,
        d.Name AS DishName,
        od.Quantity,
        d.Price * od.Quantity AS TotalPrice
    FROM Orders o
    JOIN Reservation r ON o.ReservationID = r.ID
    JOIN Customer c ON r.CustomerID = c.ID
    JOIN OrdersDetails od ON o.OrdersDetailsID = od.ID
    JOIN Dishes d ON od.DishesID = d.ID
    WHERE o.ID = p_OrderID;
END$$

CREATE DEFINER=`Shchegolkov`@`%` PROCEDURE `UpdateAdminPassword` (IN `p_username` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN `p_plain_password` VARCHAR(255))  BEGIN
    DECLARE hashed_password VARCHAR(255);
    DECLARE row_count INT DEFAULT 0;
    
    IF NOT EXISTS (SELECT 1 FROM admins WHERE username COLLATE utf8mb4_unicode_ci = p_username COLLATE utf8mb4_unicode_ci) THEN
        SELECT CONCAT('Ошибка: Администратор "', p_username, '" не найден.') AS message;
    ELSE
        SET hashed_password = SHA2(p_plain_password, 256);
        
        UPDATE admins 
        SET password = hashed_password, 
            failed_attempts = 0, 
            is_locked = 0, 
            unlock_time = NULL 
        WHERE username COLLATE utf8mb4_unicode_ci = p_username COLLATE utf8mb4_unicode_ci;
        
        SET row_count = ROW_COUNT();
        
        IF row_count > 0 THEN
            SELECT CONCAT('Пароль для "', p_username, '" успешно обновлен.') AS message;
        ELSE
            SELECT 'Ошибка при обновлении пароля.' AS message;
        END IF;
    END IF;
END$$

CREATE DEFINER=`Shchegolkov`@`%` PROCEDURE `UpdateWaiterStatus` (IN `p_WaiterID` INT, IN `p_NewStatus` VARCHAR(10))  BEGIN
    -- Проверка на существование официанта
    IF EXISTS (SELECT 1 FROM Waiter WHERE ID = p_WaiterID) THEN
        UPDATE Waiter
        SET Status = p_NewStatus
        WHERE ID = p_WaiterID;
        SELECT CONCAT('Статус официанта с ID ', p_WaiterID, ' обновлен на ', p_NewStatus) AS Message;
    ELSE
        SELECT 'Официант с таким ID не найден' AS Message;
    END IF;
END$$

--
-- Функции
--
CREATE DEFINER=`Shchegolkov`@`%` FUNCTION `GetCustomerOrderCount` (`p_CustomerID` INT) RETURNS INT(11) BEGIN
    DECLARE orderCount INT;
    
    SELECT COUNT(o.ID)
    INTO orderCount
    FROM Orders o
    JOIN Reservation r ON o.ReservationID = r.ID
    WHERE r.CustomerID = p_CustomerID;
    
    RETURN orderCount;
END$$

CREATE DEFINER=`Shchegolkov`@`%` FUNCTION `GetTotalOrderCost` (`p_OrderID` INT) RETURNS DECIMAL(10,2) BEGIN
    DECLARE totalCost DECIMAL(10,2);
    
    SELECT SUM(d.Price * od.Quantity)
    INTO totalCost
    FROM Orders o
    JOIN OrdersDetails od ON o.OrdersDetailsID = od.ID
    JOIN Dishes d ON od.DishesID = d.ID
    WHERE o.ID = p_OrderID;
    
    RETURN IFNULL(totalCost, 0);
END$$

CREATE DEFINER=`Shchegolkov`@`%` FUNCTION `IsTableAvailable` (`p_TableID` INT, `p_Date` DATE, `p_Time` TIME) RETURNS TINYINT(1) BEGIN
    DECLARE isAvailable BOOLEAN;
    
    SELECT COUNT(*) = 0
    INTO isAvailable
    FROM Reservation r
    WHERE r.TablesID = p_TableID
    AND r.Date = p_Date
    AND r.Time = p_Time;
    
    RETURN isAvailable;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `unlock_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `failed_attempts`, `is_locked`, `unlock_time`, `created_at`) VALUES
(2, 'shchegolkov', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', 0, 0, NULL, '2025-09-24 14:45:24'),
(3, 'semkin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 0, NULL, '2025-09-24 14:45:24'),
(4, 'admin', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', 0, 0, NULL, '2025-09-24 15:07:27');

-- --------------------------------------------------------

--
-- Структура таблицы `Customer`
--

CREATE TABLE `Customer` (
  `ID` int(10) NOT NULL,
  `Name` varchar(15) NOT NULL,
  `Phone` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `Customer`
--

INSERT INTO `Customer` (`ID`, `Name`, `Phone`) VALUES
(1, 'Данила', '89514567301'),
(2, 'Никита', '89045699340'),
(3, 'Александр', '89051234567'),
(4, 'Екатерина', '89162345678'),
(5, 'Михаил', '89273456789'),
(6, 'Ольга', '89384567890'),
(7, 'Сергей', '89495678901'),
(8, 'Анна', '89506789012'),
(9, 'Дмитрий', '89617890123'),
(10, 'Юлия', '89728901234'),
(11, 'Игорь', '89876543210');

-- --------------------------------------------------------

--
-- Структура таблицы `Dishes`
--

CREATE TABLE `Dishes` (
  `ID` int(10) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Category` varchar(15) NOT NULL,
  `Price` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `Dishes`
--

INSERT INTO `Dishes` (`ID`, `Name`, `Category`, `Price`) VALUES
(1, 'Макароны по-флотски', 'Горячее', '499'),
(2, 'Салат Цезарь', 'Салаты', '349'),
(3, 'Борщ', 'Горячее', '599'),
(4, 'Гороховый суп', 'Горячее', '399'),
(5, 'Лапша молочная', 'Горячее', '329'),
(6, 'Паста Карбонара', 'Паста', '600'),
(7, 'Тирамису', 'Десерты', '450'),
(8, 'Стейк Рибай', 'Мясо', '1200'),
(9, 'Греческий салат', 'Салаты', '400'),
(10, 'Лосось на гриле', 'Рыба', '900'),
(11, 'Пицца Маргарита', 'Пицца', '700'),
(12, 'Крем-суп из шампиньонов', 'Супы', '380'),
(13, 'Чизкейк', 'Десерты', '480');

-- --------------------------------------------------------

--
-- Структура таблицы `Orders`
--

CREATE TABLE `Orders` (
  `ID` int(10) NOT NULL,
  `Date` date NOT NULL,
  `Time` time NOT NULL,
  `Status` varchar(10) NOT NULL,
  `ReservationID` int(10) NOT NULL,
  `OrdersDetailsID` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `Orders`
--

INSERT INTO `Orders` (`ID`, `Date`, `Time`, `Status`, `ReservationID`, `OrdersDetailsID`) VALUES
(1, '2025-04-10', '14:00:00', 'Готов', 1, 1),
(2, '2025-04-10', '14:30:00', 'Готов', 2, 2),
(3, '2025-04-10', '15:00:00', 'Готов', 3, 3),
(4, '2025-04-10', '15:30:00', 'Готов', 4, 4),
(5, '2025-04-10', '16:00:00', 'Готов', 5, 5),
(6, '2025-04-10', '16:30:00', 'Готов', 6, 6),
(7, '2025-04-10', '17:00:00', 'Готов', 7, 7),
(8, '2025-04-10', '17:30:00', 'Готов', 8, 8),
(9, '2025-04-10', '18:00:00', 'Готов', 9, 9),
(10, '2025-04-10', '18:30:00', 'Готов', 10, 10);

--
-- Триггеры `Orders`
--
DELIMITER $$
CREATE TRIGGER `WaiterUnset` AFTER DELETE ON `Orders` FOR EACH ROW BEGIN
  DECLARE waiter_id INT;

  SELECT WaiterID INTO waiter_id
  FROM Reservation
  WHERE OrdersID = OLD.ID;

  UPDATE Waiter
  SET Status = 'Свободен'
  WHERE ID = waiter_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `OrdersDetails`
--

CREATE TABLE `OrdersDetails` (
  `ID` int(10) NOT NULL,
  `Quantity` int(20) NOT NULL,
  `OrdersID` int(10) NOT NULL,
  `DishesID` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `OrdersDetails`
--

INSERT INTO `OrdersDetails` (`ID`, `Quantity`, `OrdersID`, `DishesID`) VALUES
(1, 2, 1, 1),
(2, 1, 2, 2),
(3, 3, 3, 3),
(4, 2, 4, 4),
(5, 2, 5, 5),
(6, 2, 6, 6),
(7, 1, 7, 7),
(8, 3, 8, 8),
(9, 3, 9, 11),
(10, 1, 10, 13);

-- --------------------------------------------------------

--
-- Структура таблицы `Reservation`
--

CREATE TABLE `Reservation` (
  `ID` int(10) NOT NULL,
  `CustomerID` int(10) NOT NULL,
  `TablesID` int(10) NOT NULL,
  `Date` date NOT NULL,
  `Time` time NOT NULL,
  `WaiterID` int(10) NOT NULL,
  `OrdersID` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `Reservation`
--

INSERT INTO `Reservation` (`ID`, `CustomerID`, `TablesID`, `Date`, `Time`, `WaiterID`, `OrdersID`) VALUES
(2, 2, 2, '2025-04-10', '14:30:00', 2, 2),
(3, 3, 3, '2025-04-10', '15:00:00', 3, 3),
(4, 4, 4, '2025-04-10', '15:30:00', 4, 4),
(5, 5, 5, '2025-04-10', '16:00:00', 5, 5),
(6, 6, 6, '2025-04-10', '16:30:00', 6, 6),
(7, 7, 7, '2025-04-10', '17:00:00', 7, 7),
(8, 8, 8, '2025-04-10', '17:30:00', 8, 8),
(9, 9, 9, '2025-04-10', '18:00:00', 9, 9),
(10, 10, 10, '2025-04-10', '18:30:00', 10, 10);

--
-- Триггеры `Reservation`
--
DELIMITER $$
CREATE TRIGGER `CheckTableAvailability` BEFORE INSERT ON `Reservation` FOR EACH ROW BEGIN
    DECLARE table_count INT;
    
    SELECT COUNT(*) INTO table_count
    FROM Reservation
    WHERE TablesID = NEW.TablesID
    AND `Date` = NEW.`Date`
    AND `Time` = NEW.`Time`;
    
    IF table_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Столик уже забронирован на указанное время';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `UpdateWaiterStatusOnReservation` AFTER INSERT ON `Reservation` FOR EACH ROW BEGIN
    UPDATE Waiter
    SET Status = 'Занят'
    WHERE ID = NEW.WaiterID
    AND Status = 'Свободен';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `Tables`
--

CREATE TABLE `Tables` (
  `ID` int(10) NOT NULL,
  `NumberOfSeats` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `Tables`
--

INSERT INTO `Tables` (`ID`, `NumberOfSeats`) VALUES
(1, 6),
(2, 2),
(3, 4),
(4, 2),
(5, 8),
(6, 3),
(7, 6),
(8, 4),
(9, 2),
(10, 5);

-- --------------------------------------------------------

--
-- Структура таблицы `Waiter`
--

CREATE TABLE `Waiter` (
  `ID` int(10) NOT NULL,
  `Name` varchar(15) NOT NULL,
  `Surname` varchar(20) NOT NULL,
  `Status` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `Waiter`
--

INSERT INTO `Waiter` (`ID`, `Name`, `Surname`, `Status`) VALUES
(1, 'Максим', 'Щегольков', 'Свободен'),
(2, 'Иван', 'Сёмкин', 'Свободен'),
(3, 'Владимир', 'Сидоров', 'Перерыв'),
(4, 'Мария', 'Козлова', 'Свободен'),
(5, 'Павел', 'Морозов', 'Занят'),
(6, 'Наталья', 'Смирнова', 'Перерыв'),
(7, 'Артём', 'Волков', 'Свободен'),
(8, 'Оксана', 'Лебедева', 'Занят'),
(9, 'Игорь', 'Фёдоров', 'Перерыв'),
(10, 'Светлана', 'Романова', 'Свободен');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Индексы таблицы `Customer`
--
ALTER TABLE `Customer`
  ADD PRIMARY KEY (`ID`);

--
-- Индексы таблицы `Dishes`
--
ALTER TABLE `Dishes`
  ADD PRIMARY KEY (`ID`);

--
-- Индексы таблицы `Orders`
--
ALTER TABLE `Orders`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `ReservationID` (`ReservationID`),
  ADD KEY `OrdersDetailsID` (`OrdersDetailsID`);

--
-- Индексы таблицы `OrdersDetails`
--
ALTER TABLE `OrdersDetails`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `OrdersID` (`OrdersID`,`DishesID`),
  ADD KEY `DishID` (`DishesID`);

--
-- Индексы таблицы `Reservation`
--
ALTER TABLE `Reservation`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `CustomerID` (`CustomerID`,`TablesID`,`WaiterID`),
  ADD KEY `WaiterID` (`WaiterID`),
  ADD KEY `TablesID` (`TablesID`),
  ADD KEY `OrdersID` (`OrdersID`);

--
-- Индексы таблицы `Tables`
--
ALTER TABLE `Tables`
  ADD PRIMARY KEY (`ID`);

--
-- Индексы таблицы `Waiter`
--
ALTER TABLE `Waiter`
  ADD PRIMARY KEY (`ID`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `Customer`
--
ALTER TABLE `Customer`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `Dishes`
--
ALTER TABLE `Dishes`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT для таблицы `Orders`
--
ALTER TABLE `Orders`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `OrdersDetails`
--
ALTER TABLE `OrdersDetails`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `Tables`
--
ALTER TABLE `Tables`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `Waiter`
--
ALTER TABLE `Waiter`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `Orders`
--
ALTER TABLE `Orders`
  ADD CONSTRAINT `Orders_ibfk_2` FOREIGN KEY (`OrdersDetailsID`) REFERENCES `OrdersDetails` (`ID`);

--
-- Ограничения внешнего ключа таблицы `OrdersDetails`
--
ALTER TABLE `OrdersDetails`
  ADD CONSTRAINT `OrdersDetails_ibfk_1` FOREIGN KEY (`DishesID`) REFERENCES `Dishes` (`ID`);

--
-- Ограничения внешнего ключа таблицы `Reservation`
--
ALTER TABLE `Reservation`
  ADD CONSTRAINT `Reservation_ibfk_1` FOREIGN KEY (`CustomerID`) REFERENCES `Customer` (`ID`),
  ADD CONSTRAINT `Reservation_ibfk_2` FOREIGN KEY (`WaiterID`) REFERENCES `Waiter` (`ID`),
  ADD CONSTRAINT `Reservation_ibfk_3` FOREIGN KEY (`TablesID`) REFERENCES `Tables` (`ID`),
  ADD CONSTRAINT `Reservation_ibfk_4` FOREIGN KEY (`OrdersID`) REFERENCES `Orders` (`ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
