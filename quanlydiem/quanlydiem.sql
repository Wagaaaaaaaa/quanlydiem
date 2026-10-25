-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 10, 2026 at 10:42 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `quanlydiem`
--

-- --------------------------------------------------------

--
-- Table structure for table `diem`
--

CREATE TABLE `diem` (
  `MaSV` varchar(20) NOT NULL,
  `MaHP` varchar(20) NOT NULL,
  `DiemCC` float DEFAULT NULL COMMENT 'Điểm chuyên cần',
  `DiemKT` float DEFAULT NULL COMMENT 'Điểm kiểm tra/bài tập',
  `DiemTL` float DEFAULT NULL COMMENT 'Điểm thảo luận/tiểu luận',
  `DiemGK` float DEFAULT NULL COMMENT 'Điểm giữa kỳ',
  `DiemQT` float DEFAULT NULL,
  `DiemThi` float DEFAULT NULL,
  `DiemTK` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `diem`
--

INSERT INTO `diem` (`MaSV`, `MaHP`, `DiemCC`, `DiemKT`, `DiemTL`, `DiemGK`, `DiemQT`, `DiemThi`, `DiemTK`) VALUES
('SV001', '1ML001DC', 10, 10, 10, 10, 10, 10, 10),
('SV001', '1TN101CD', 10, 10, 10, 10, 10, 10, 10),
('SV001', '1TN102CD', 10, 10, 10, 10, 10, 10, 10),
('SV001', '2ML002DC', 10, 10, 10, 10, 10, 10, 10),
('SV001', '3DC004DC', 10, 9, 8, 10, 9.7, NULL, NULL),
('SV001', '3DC005DC', 10, 9, 9, 10, 9.8, NULL, NULL),
('SV001', '3ML005DC', 10, 10, 10, 9, 9.5, NULL, NULL),
('SV001', '3ML006DC', 10, 10, 10, 10, 10, 10, 10),
('SV001', '3NN003DC', 10, 9, 9, 10, 9.8, NULL, NULL),
('SV001', '3TN102CD', 10, 10, 8, 9, 9.3, NULL, NULL),
('SV001', '3TN103CD', 10, 10, 10, 10, 10, 10, 10),
('SV001', '3TN106CD', 10, 10, 10, 10, 10, NULL, NULL),
('SV001', '4TN105CD', 10, 10, 10, 10, 10, 10, 10),
('SV001', '4TN107CD', 10, 10, 10, 10, 10, 10, 10),
('SV002', '1ML001DC', 9, 8.5, 8, 8.5, 8.6, 8, 8.2),
('SV002', '1TN101CD', 8.5, 8, 8, 8, 8.1, 7.5, 7.7),
('SV002', '1TN102CD', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV002', '2ML002DC', 10, 9, 8.5, 9, 9.1, 8.5, 8.7),
('SV002', '3DC004DC', 9, 7.5, 8, 7.5, 7.9, 7, 7.3),
('SV002', '3DC005DC', 8.5, 8, 7.5, 8, 8, 8, 8),
('SV002', '3ML005DC', 10, 8.5, 9, 9, 9.1, 8.5, 8.7),
('SV002', '3ML006DC', 9.5, 9, 8.5, 9, 9, 8.5, 8.7),
('SV002', '3NN003DC', 9, 8, 8, 8.5, 8.4, 8, 8.1),
('SV002', '3TN102CD', 9.5, 8.5, 9, 8.5, 8.8, 8.5, 8.6),
('SV002', '3TN103CD', 10, 9.5, 9, 9, 9.3, 9, 9.1),
('SV002', '3TN106CD', 9.5, 9, 8.5, 9, 9, 8.5, 8.7),
('SV002', '4TN105CD', 10, 9, 9, 9.5, 9.4, 9, 9.1),
('SV002', '4TN107CD', 9.5, 8.5, 9, 8.5, 8.8, 8.5, 8.6),
('SV003', '2DC011DC', 9, 8, 8.5, 8, 8.3, 7.5, 7.7),
('SV003', '2ML004DC', 10, 9, 8.5, 9, 9.1, 8.5, 8.7),
('SV003', '3DC004DC', 9, 7.5, 8, 7.5, 7.9, 7, 7.3),
('SV003', '3DC005DC', 8.5, 8, 7.5, 8, 8, 8, 8),
('SV003', '3DT103DH', 9, 8, 8.5, 8.5, 8.5, 8, 8.2),
('SV003', '3DT106DH', 9, 8.5, 8, 8.5, 8.5, 8, 8.2),
('SV003', '3DT201CD', 10, 9, 9, 9.5, 9.3, 9, 9.1),
('SV003', '3DT203CD', 9.5, 8.5, 9, 8.5, 8.8, 8.5, 8.6),
('SV003', '4DC001CS', 9, 8, 8.5, 8, 8.3, 7.5, 7.7),
('SV003', '4DC002CS', 10, 8.5, 8, 8.5, 8.7, 8, 8.2),
('SV003', '4DC016DC', 9, 7.5, 8, 7, 7.7, 7, 7.2),
('SV003', '4NN001DC', 10, 8, 8, 8.5, 8.6, 8, 8.2),
('SV003', '4NN002DC', 10, 8.5, 9, 8.5, 8.9, 8.5, 8.6),
('SV003', '4TN305DH', 10, 9.5, 9, 9, 9.3, 9, 9.1),
('SV004', '2DC011DC', 8, 7, 7.5, 7, 7.3, 6.5, 6.7),
('SV004', '2ML004DC', 9.5, 8.5, 8, 8.5, 8.6, 8, 8.2),
('SV004', '3DC004DC', 8, 6.5, 7, 6.5, 6.9, 6, 6.3),
('SV004', '3DC005DC', 8, 7, 7, 7.5, 7.4, 6.5, 6.8),
('SV004', '3DT103DH', 8.5, 7.5, 8, 7.5, 7.8, 7, 7.2),
('SV004', '3DT106DH', 8.5, 7.5, 8, 7.5, 7.8, 7, 7.2),
('SV004', '3DT201CD', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV004', '3DT203CD', 8.5, 8, 8, 8, 8.1, 7.5, 7.7),
('SV004', '4DC001CS', 8.5, 7.5, 7, 8, 7.8, 6.5, 6.9),
('SV004', '4DC002CS', 9, 7, 7.5, 7, 7.5, 7, 7.2),
('SV004', '4DC016DC', 8.5, 6.5, 7, 6.5, 7, 6, 6.3),
('SV004', '4NN001DC', 9, 7.5, 8, 7.5, 7.9, 7.5, 7.6),
('SV004', '4NN002DC', 9, 8, 8, 8, 8.2, 7.5, 7.7),
('SV004', '4TN305DH', 10, 8.5, 9, 8.5, 8.9, 8.5, 8.6),
('SV005', '1TN101CD', 10, 10, 8, 9, 9.3, NULL, NULL),
('SV005', '2DC007DC', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV005', '3DC004DC', 10, 9, 8.5, 9, 9.1, 8.5, 8.7),
('SV005', '3DC005DC', 9.5, 8.5, 9, 8.5, 8.8, 8, 8.2),
('SV005', '3DT107DH', 9.5, 9, 9, 9.5, 9.3, 9, 9.1),
('SV005', '3DT110DH', 10, 9.5, 9, 9.5, 9.5, 9, 9.2),
('SV005', '3DT130DH', 9, 8, 8, 8.5, 8.4, 8, 8.1),
('SV005', '3ML005DC', 10, 8.5, 9, 9, 9.1, 8.5, 8.7),
('SV005', '4DC016DC', 9, 8, 8.5, 8, 8.3, 8.5, 8.4),
('SV005', '4DN131DH', 10, 9, 9, 9.5, 9.4, 9, 9.1),
('SV005', '4DT104DH', 9.5, 8.5, 9, 8.5, 8.8, 8.5, 8.6),
('SV005', '4NN009DC', 9.5, 9, 8.5, 9, 9, 8.5, 8.7),
('SV006', '1TN101CD', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV006', '2DC007DC', 8.5, 7.5, 7, 7.5, 7.6, 7, 7.2),
('SV006', '3DC004DC', 9, 8, 8, 8, 8.2, 7.5, 7.7),
('SV006', '3DC005DC', 8.5, 7.5, 8, 7.5, 7.8, 7, 7.2),
('SV006', '3DT107DH', 8.5, 8, 8, 8, 8.1, 7.5, 7.7),
('SV006', '3DT110DH', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV006', '3DT130DH', 8, 7, 7.5, 7, 7.3, 7, 7.1),
('SV006', '3ML005DC', 9, 8, 8, 8, 8.2, 7.5, 7.7),
('SV006', '4DC016DC', 8, 7, 7.5, 7, 7.3, 7, 7.1),
('SV006', '4DN131DH', 9, 8, 8.5, 8, 8.3, 7.5, 7.7),
('SV006', '4DT104DH', 8.5, 7.5, 8, 8, 8, 7.5, 7.7),
('SV006', '4NN009DC', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV007', '1ML001DC', 10, 9, 8, 10, 9.7, NULL, NULL),
('SV007', '1TN102CD', 8, 7, 8, 6, 6.9, NULL, NULL),
('SV007', '3DT106DH', 10, 9.5, 9, 9.5, 9.5, 9.5, 9.5),
('SV007', '3DT109DH', 9.5, 9, 9.5, 9, 9.2, 9, 9.1),
('SV007', '3DT110DH', 10, 9, 9, 9, 9.2, 8.5, 8.7),
('SV007', '3DT132DH', 10, 9.5, 9.5, 9.5, 9.6, 9.5, 9.5),
('SV007', '3DT204CD', 10, 10, 9.5, 9.5, 9.7, 9, 9.2),
('SV007', '3DT206CD', 9.5, 9, 9, 9.5, 9.3, 8.5, 8.7),
('SV007', '4DN131DH', 10, 9.5, 9, 9.5, 9.5, 9, 9.2),
('SV007', '4DT104DH', 10, 9, 9.5, 9, 9.3, 9, 9.1),
('SV007', '4DT139DH', 9.5, 9, 9, 9.5, 9.3, 9, 9.1),
('SV008', '1ML001DC', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV008', '1TN102CD', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV008', '3DT106DH', 9, 8.5, 8.5, 8, 8.4, 8, 8.1),
('SV008', '3DT109DH', 8.5, 8, 8, 8.5, 8.3, 7.5, 7.7),
('SV008', '3DT110DH', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV008', '3DT132DH', 8.5, 8, 8, 8.5, 8.3, 8, 8.1),
('SV008', '3DT204CD', 9, 8.5, 9, 8.5, 8.7, 8, 8.2),
('SV008', '3DT206CD', 8.5, 8, 8, 8, 8.1, 7.5, 7.7),
('SV008', '4DN131DH', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV008', '4DT104DH', 8.5, 8, 8, 8.5, 8.3, 7.5, 7.7),
('SV008', '4DT139DH', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV009', '1TN101CD', 10, 9, 8.5, 9, 9.1, 8.5, 8.7),
('SV009', '1TN102CD', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV009', '3DC004DC', 10, 8.5, 8, 8.5, 8.7, 8, 8.2),
('SV009', '3TN002DH', 9.5, 9, 8.5, 9, 9, 8.5, 8.7),
('SV009', '3TN106CD', 10, 8.5, 9, 8.5, 8.9, 8.5, 8.6),
('SV009', '3TN109CD', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV009', '3TN201CD', 10, 9, 9.5, 9, 9.3, 9, 9.1),
('SV009', '4TN103CD', 9.5, 9, 9, 9, 9.1, 8.5, 8.7),
('SV009', '4TN123DH', 10, 9.5, 9, 9.5, 9.5, 9, 9.2),
('SV010', '1TN101CD', 9, 8, 8, 8.5, 8.3, 8, 8.1),
('SV010', '1TN102CD', 8.5, 7.5, 8, 7.5, 7.8, 7, 7.2),
('SV010', '3DC004DC', 9, 7.5, 8, 7.5, 7.9, 7, 7.3),
('SV010', '3TN002DH', 9, 8, 8, 8, 8.2, 7.5, 7.7),
('SV010', '3TN106CD', 8.5, 7.5, 8, 8, 8, 7.5, 7.7),
('SV010', '3TN109CD', 8, 7, 7.5, 7, 7.3, 7, 7.1),
('SV010', '3TN201CD', 9, 8.5, 8.5, 8, 8.4, 8, 8.1),
('SV010', '4TN103CD', 9, 8, 8.5, 8, 8.3, 7.5, 7.7),
('SV010', '4TN123DH', 9.5, 8.5, 9, 8.5, 8.9, 8.5, 8.6),
('SV011', '1TN101CD', 10, 9, 9, 9, 9.2, 9, 9.1),
('SV011', '3TN103CD', 10, 9, 8.5, 9, 9.1, 8.5, 8.7),
('SV011', '3TN104CD', 9.5, 8.5, 9, 9, 9, 8.5, 8.7),
('SV011', '3TN106CD', 9.5, 8.5, 9, 8.5, 8.8, 8.5, 8.6),
('SV011', '4TN003CD', 10, 9, 9.5, 9, 9.3, 9, 9.1),
('SV011', '4TN105DH', 9, 8.5, 8, 8.5, 8.5, 8, 8.2),
('SV011', '4TN106DH', 10, 9.5, 9, 9.5, 9.5, 9, 9.2),
('SV012', '1TN101CD', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV012', '3TN103CD', 9, 8, 8, 8, 8.2, 7.5, 7.7),
('SV012', '3TN104CD', 8.5, 8, 8, 8.5, 8.3, 7.5, 7.7),
('SV012', '3TN106CD', 8.5, 7.5, 8, 7.5, 7.8, 7.5, 7.6),
('SV012', '4TN003CD', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV012', '4TN105DH', 8.5, 7.5, 8, 7.5, 7.8, 7, 7.2),
('SV012', '4TN106DH', 9, 8.5, 8.5, 8.5, 8.6, 8, 8.2),
('SV013', '3TN105CD', 9.5, 9, 8.5, 9, 9, 8.5, 8.7),
('SV013', '4TN105CD', 10, 9, 9, 9.5, 9.3, 9, 9.1),
('SV013', '4TN107CD', 10, 9.5, 9, 9, 9.3, 9, 9.1),
('SV013', '4TN111DH', 10, 9, 9.5, 9, 9.3, 8.5, 8.8),
('SV013', '4TN120DH', 9, 8.5, 8.5, 8.5, 8.6, 8, 8.2),
('SV014', '3TN105CD', 9, 8, 8, 8.5, 8.4, 8, 8.1),
('SV014', '4TN105CD', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV014', '4TN107CD', 8.5, 8, 8, 8, 8.1, 7.5, 7.7),
('SV014', '4TN111DH', 8.5, 7.5, 8, 7.5, 7.8, 7, 7.2),
('SV014', '4TN120DH', 9, 8, 8, 8, 8.2, 7.5, 7.7),
('SV017', '4DC016DC', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV017', '4DN131DH', 9.5, 9, 8.5, 9, 9, 8.5, 8.7),
('SV017', '4DT104DH', 9, 8.5, 8.5, 8.5, 8.6, 8, 8.2),
('SV018', '4DC016DC', 8.5, 7.5, 8, 7.5, 7.8, 7.5, 7.6),
('SV018', '4DN131DH', 9, 8, 8, 8.5, 8.3, 8, 8.1),
('SV018', '4DT104DH', 8.5, 8, 7.5, 8, 8, 7.5, 7.7),
('SV021', '1ML001DC', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV021', '1TN101CD', 10, 9, 8.5, 9, 9.1, 8.5, 8.7),
('SV021', '1TN102CD', 9, 8.5, 8, 8.5, 8.5, 8, 8.2),
('SV021', '2ML002DC', 9.5, 8.5, 9, 9, 9, 8.5, 8.7),
('SV021', '3TN103CD', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV022', '1ML001DC', 8.5, 7.5, 8, 8, 8, 7.5, 7.7),
('SV022', '1TN101CD', 9, 8.5, 8, 8, 8.4, 8, 8.1),
('SV022', '1TN102CD', 8, 7, 7.5, 7.5, 7.4, 7, 7.1),
('SV022', '2ML002DC', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV022', '3TN103CD', 8.5, 8, 8, 8.5, 8.3, 7.5, 7.7),
('SV023', '2DC011DC', 9.5, 8.5, 9, 8.5, 8.8, 8.5, 8.6),
('SV023', '2ML004DC', 10, 9, 9, 9.5, 9.4, 9, 9.1),
('SV023', '3DC004DC', 9, 8, 8, 8.5, 8.3, 8, 8.1),
('SV023', '3DT103DH', 9.5, 8.5, 9, 8.5, 8.8, 8.5, 8.6),
('SV023', '3DT106DH', 10, 9, 9, 9, 9.2, 9, 9.1),
('SV023', '3DT204CD', 9.5, 9, 9.5, 9, 9.2, 9, 9.1),
('SV023', '4DC001CS', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV024', '2DC011DC', 8.5, 7.5, 8, 7.5, 7.8, 7, 7.2),
('SV024', '2ML004DC', 9, 8, 8, 8.5, 8.3, 8, 8.1),
('SV024', '3DC004DC', 8.5, 7, 7.5, 7, 7.4, 7, 7.1),
('SV024', '3DT103DH', 8, 7.5, 8, 7.5, 7.7, 7, 7.2),
('SV024', '3DT106DH', 8.5, 8, 8, 8, 8.1, 7.5, 7.7),
('SV024', '3DT204CD', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV024', '4DC001CS', 8.5, 7.5, 8, 8, 8, 7.5, 7.7),
('SV025', '3DT106DH', 9.5, 9, 9, 9, 9.1, 9, 9),
('SV025', '3DT109DH', 9, 8.5, 8.5, 8.5, 8.6, 8, 8.2),
('SV025', '3DT110DH', 9.5, 8.5, 9, 9, 9, 8.5, 8.7),
('SV025', '4DN131DH', 10, 9, 9.5, 9, 9.3, 9, 9.1),
('SV025', '4DT104DH', 9.5, 8.5, 9, 8.5, 8.8, 8.5, 8.6),
('SV026', '3DT106DH', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV026', '3DT109DH', 8.5, 8, 8, 8, 8.1, 7.5, 7.7),
('SV026', '3DT110DH', 8.5, 7.5, 8, 8, 8, 7.5, 7.7),
('SV026', '4DN131DH', 9, 8.5, 8, 8.5, 8.5, 8, 8.2),
('SV026', '4DT104DH', 8.5, 7.5, 8, 7.5, 7.8, 7, 7.2),
('SV027', '1TN101CD', 9.5, 9, 8.5, 9, 9, 8.5, 8.7),
('SV027', '1TN102CD', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV027', '3DC004DC', 9.5, 8.5, 8, 8.5, 8.6, 8, 8.2),
('SV027', '3TN002DH', 9, 8.5, 8.5, 9, 8.8, 8.5, 8.6),
('SV027', '3TN106CD', 10, 9, 9, 9, 9.2, 8.5, 8.8),
('SV028', '1TN101CD', 8.5, 7.5, 8, 8, 8, 7.5, 7.7),
('SV028', '1TN102CD', 8, 7, 7.5, 7, 7.3, 7, 7.1),
('SV028', '3DC004DC', 8.5, 7.5, 8, 7.5, 7.8, 7, 7.2),
('SV028', '3TN002DH', 8.5, 8, 8, 8, 8.1, 7.5, 7.7),
('SV028', '3TN106CD', 8, 7.5, 8, 7.5, 7.7, 7, 7.2),
('SV029', '1TN101CD', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV029', '2DC007DC', 9.5, 8.5, 9, 8.5, 8.8, 8.5, 8.6),
('SV029', '3DC004DC', 10, 9, 8.5, 9, 9.1, 8.5, 8.7),
('SV029', '3DC005DC', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV029', '3DT107DH', 9.5, 8.5, 9, 9, 9, 8.5, 8.7),
('SV029', '3DT110DH', 9, 9, 8.5, 9, 8.9, 8.5, 8.6),
('SV029', '3DT130DH', 9, 8.5, 8, 8.5, 8.5, 8, 8.2),
('SV029', '3ML005DC', 9.5, 8.5, 9, 9, 9, 8.5, 8.7),
('SV030', '1TN101CD', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV030', '2DC007DC', 8.5, 7.5, 7.5, 7.5, 7.7, 7, 7.2),
('SV030', '3DC004DC', 8.5, 8, 8, 8, 8.1, 7.5, 7.7),
('SV030', '3DC005DC', 8, 7, 7.5, 7.5, 7.4, 7, 7.1),
('SV030', '3DT107DH', 8, 7.5, 7.5, 7.5, 7.6, 7, 7.2),
('SV030', '3DT110DH', 8.5, 8, 8, 8, 8.1, 7.5, 7.7),
('SV030', '3DT130DH', 8, 7.5, 7.5, 7, 7.4, 7, 7.1),
('SV030', '3ML005DC', 8.5, 7.5, 8, 8, 8, 7.5, 7.7),
('SV031', '1CK184DH', 9, 8.5, 8, 8.5, 8.5, 8, 8.2),
('SV031', '4DC016DC', 8.5, 8, 8, 8.5, 8.3, 8, 8.1),
('SV032', '1CK184DH', 8.5, 8, 8.5, 8, 8.2, 8, 8.1),
('SV032', '4DC016DC', 9, 8.5, 8, 8, 8.4, 8.5, 8.5),
('SV033', '1CK184DH', 10, 9, 9.5, 9, 9.3, 9, 9.1),
('SV033', '4DC016DC', 9.5, 9, 9, 9, 9.1, 9, 9),
('SV034', '1CK184DH', 8.5, 7.5, 8, 8, 8, 7.5, 7.7),
('SV034', '3DC004DC', 8, 7.5, 7.5, 7, 7.4, 7, 7.1),
('SV035', '1CK184DH', 9, 8.5, 8.5, 8.5, 8.6, 8.5, 8.5),
('SV035', '3DC004DC', 9, 8, 8.5, 8.5, 8.5, 8, 8.2),
('SV036', '4DN131DH', 9, 8, 8.5, 8, 8.3, 8, 8.1),
('SV036', '4DT104DH', 8.5, 7.5, 8, 8, 8, 7.5, 7.7),
('SV037', '4DN131DH', 9.5, 9, 9, 9, 9.1, 8.5, 8.7),
('SV037', '4DT104DH', 9, 8.5, 9, 8.5, 8.7, 8.5, 8.6),
('SV038', '4DN131DH', 8.5, 8, 8, 8.5, 8.3, 8, 8.1),
('SV038', '4DT104DH', 8, 7.5, 7.5, 7.5, 7.6, 7, 7.2),
('SV039', '3DT106DH', 9, 8.5, 8, 8.5, 8.5, 8, 8.2),
('SV039', '3DT107DH', 8.5, 8, 8.5, 8, 8.2, 8, 8.1),
('SV040', '3DT106DH', 9.5, 9, 8.5, 9, 9, 8.5, 8.7),
('SV040', '3DT107DH', 9, 8.5, 9, 8.5, 8.7, 8.5, 8.6),
('SV1', '3DC012DC', 6, 5, 2, 8, 6.5, 10, 8.6),
('SV1', '4TQ012DC', 10, NULL, NULL, NULL, 3, NULL, NULL),
('SV10', '3DC012DC', 4, 6, 8, 5, 5.1, 7, 6.24),
('SV10', '4TQ012DC', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV11', '3DC012DC', 5, 6, 7, 7, 6.3, 7, 6.72),
('SV11', '4TQ012DC', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV12', '3DC012DC', 8, 8, 5, 8, 7.7, 7, 7.28),
('SV12', '4TQ012DC', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV13', '3DC012DC', 4, 8, 2, 7, 5.7, 6, 5.88),
('SV13', '4TQ012DC', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV14', '3DC012DC', 7, 4, 8, 8, 7.3, 8, 7.72),
('SV14', '4TQ012DC', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV15', '3DC012DC', 6, 8, 9, 8, 7.5, 8, 7.8),
('SV15', '4TQ012DC', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV16', '3DC012DC', 7, 7, 9, 6, 6.7, 3, 4.48),
('SV16', '4TQ012DC', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV17', '3DC012DC', 8, 8, 8, 6, 7, 8, 7.6),
('SV17', '4TQ012DC', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('SV18', '3DC012DC', 8, 10, 6, NULL, 4, 9, 7),
('SV18', '4TQ012DC', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hocphan`
--

CREATE TABLE `hocphan` (
  `MaHP` varchar(20) NOT NULL,
  `TenHP` varchar(100) NOT NULL,
  `SoTC` int(11) NOT NULL,
  `HocKy` int(11) NOT NULL,
  `NganhHoc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hocphan`
--

INSERT INTO `hocphan` (`MaHP`, `TenHP`, `SoTC`, `HocKy`, `NganhHoc`) VALUES
('1CDR001TA', 'Tiếng Anh CĐR (Học)', 1, 6, NULL),
('1CDR002TA', 'Tiếng Anh chuẩn đầu ra (Thi)', 1, 6, NULL),
('1CK184DH', 'Nhập môn ngành cơ điện tử', 3, 1, 'Công nghệ kỹ thuật máy tính'),
('1CK191DH', 'Cơ khí đại cương', 3, 3, 'Công nghệ kỹ thuật máy tính'),
('1CK193DH', 'Cơ học máy', 3, 4, 'Công nghệ kỹ thuật máy tính'),
('1CK194DH', 'Hệ thống cơ điện tử', 4, 6, 'Công nghệ kỹ thuật máy tính'),
('1CK301DH', 'Đồ án cơ học máy', 1, 6, 'Công nghệ kỹ thuật máy tính'),
('1CK307DH', 'Đồ án chuyên ngành cơ điện tử', 1, 6, 'Công nghệ kỹ thuật máy tính'),
('1CK308DH', 'Đồ án tốt nghiệp', 10, 8, 'Công nghệ kỹ thuật máy tính'),
('1DN155DH', 'Truyền động điện', 3, 6, 'Công nghệ kỹ thuật máy tính'),
('1DN156DH', 'Điều khiển máy công cụ và máy điều khiển số', 3, 6, 'Công nghệ kỹ thuật máy tính'),
('1DN164DH', 'Kỹ thuật điều khiển tự động', 3, 5, 'Công nghệ kỹ thuật máy tính'),
('1DN245DH', 'Lắp đặt thiết bị tự động hóa và kết nối truyền thông', 3, 4, 'Công nghệ kỹ thuật máy tính'),
('1DN246DH', 'Điều khiển Logic và lập trình PLC', 3, 5, 'Công nghệ kỹ thuật máy tính'),
('1ML001DC', 'Triết học Mác - Lênin', 3, 1, NULL),
('1TN101CD', 'Nhập môn Lập trình', 3, 1, 'Kỹ thuật phần mềm'),
('1TN102CD', 'Toán rời rạc', 3, 1, 'Công nghệ thông tin'),
('2DC007DC', 'Xác suất thống kê', 2, 2, NULL),
('2DC011DC', 'Hóa học đại cương 1', 2, 2, NULL),
('2ML002DC', 'Tư tưởng Hồ Chí Minh', 3, 2, NULL),
('2ML004DC', 'Pháp luật đại cương', 2, 1, NULL),
('2ML005DC', 'Kinh tế học đại cương', 2, 4, NULL),
('2TN010DC', 'Mạng máy tính', 2, 3, 'Công nghệ thông tin'),
('2TN033DH', 'Lập trình song song', 2, 6, 'Công nghệ thông tin'),
('2TQ001DC', 'Đường lối Quốc phòng và An ninh của Đảng Cộng sản Việt Nam', 3, 1, NULL),
('2TQ002DC', 'Công tác quốc phòng, an ninh', 2, 1, NULL),
('3CK111DH', 'Thiết kế cơ khí trên máy tính', 2, 5, 'Công nghệ kỹ thuật máy tính'),
('3CK112DH', 'Cơ lý thuyết', 2, 3, 'Công nghệ kỹ thuật máy tính'),
('3CK128DH', 'Ứng dụng CAE', 2, 6, 'Công nghệ kỹ thuật máy tính'),
('3CK201CD', 'Gia công nguội - Hàn cơ bản', 2, 2, 'Công nghệ kỹ thuật máy tính'),
('3CK203CD', 'Tiện trụ trơn, trụ bậc', 2, 1, 'Công nghệ kỹ thuật máy tính'),
('3CK205CD', 'Phay mặt phẳng', 2, 3, 'Công nghệ kỹ thuật máy tính'),
('3DC004DC', 'Toán cao cấp 1', 2, 1, NULL),
('3DC005DC', 'Toán cao cấp 2', 2, 2, NULL),
('3DC012DC', 'Phép biến đổi Laplace và phương pháp tính', 2, 4, NULL),
('3DL145DH', 'Truyền động thủy lực khí nén', 2, 6, 'Công nghệ kỹ thuật máy tính'),
('3DN139DH', 'Hệ thống thông tin công nghiệp', 2, 7, 'Công nghệ kỹ thuật máy tính'),
('3DN170DH', 'Công nghệ RFID', 2, 7, 'Công nghệ kỹ thuật máy tính'),
('3DN203CD', 'Lắp đặt mạch điện điều khiển động cơ điện dùng rơ le, công tắc tơ', 2, 4, 'Công nghệ kỹ thuật máy tính'),
('3DN234DH', 'Lắp đặt hệ thống đo và điều khiển bằng máy tính', 2, 5, 'Công nghệ kỹ thuật máy tính'),
('3DT103DH', 'Điện tử tương tự', 2, 2, 'Công nghệ kỹ thuật máy tính'),
('3DT106DH', 'Vi điều khiển', 3, 4, 'Công nghệ kỹ thuật máy tính'),
('3DT107DH', 'Thiết kế số bằng ngôn ngữ mô tả phần cứng HDL', 2, 5, 'Công nghệ kỹ thuật máy tính'),
('3DT109DH', 'Lập trình C cho vi điều khiển', 2, 4, 'Công nghệ kỹ thuật máy tính'),
('3DT110DH', 'Ngôn ngữ lập trình', 2, 4, 'Công nghệ kỹ thuật máy tính'),
('3DT111DH', 'Xử lý số tín hiệu', 2, 5, 'Công nghệ kỹ thuật máy tính'),
('3DT112DH', 'Kỹ thuật truyền số liệu và mạng', 2, 5, 'Công nghệ kỹ thuật máy tính'),
('3DT130DH', 'Cơ sở lý thuyết truyền tin', 2, 3, 'Công nghệ kỹ thuật máy tính'),
('3DT132DH', 'Hệ thống nhúng', 3, 6, 'Công nghệ kỹ thuật máy tính'),
('3DT201CD', 'Lắp mạch điện tử cơ bản', 2, 2, 'Công nghệ kỹ thuật máy tính'),
('3DT202CD', 'Lắp mạch điện tử tương tự', 2, 2, 'Công nghệ kỹ thuật máy tính'),
('3DT203CD', 'Lắp mạch điện tử số', 2, 3, 'Công nghệ kỹ thuật máy tính'),
('3DT204CD', 'Lắp mạch cảm biến', 2, 4, 'Công nghệ kỹ thuật máy tính'),
('3DT206CD', 'Lập trình vi điều khiển cơ bản', 2, 4, 'Công nghệ kỹ thuật máy tính'),
('3DT210DH', 'Lập trình ASIC', 2, 5, 'Công nghệ kỹ thuật máy tính'),
('3DT215CD', 'Chế tạo mạch điện tử', 2, 3, 'Công nghệ kỹ thuật máy tính'),
('3DT220CD', 'Thiết kế, lắp đặt hệ thống IOT', 2, 6, 'Công nghệ thông tin'),
('3DT231DH', 'Lập trình DSP', 2, 5, 'Công nghệ kỹ thuật máy tính'),
('3DT232DH', 'Lập trình hệ thống nhúng', 2, 6, 'Công nghệ kỹ thuật máy tính'),
('3DT240DH', 'Lập trình ứng dụng điều khiển cho thiết bị di động', 4, 7, 'Công nghệ kỹ thuật máy tính'),
('3ML002DC', 'Văn hóa Việt Nam', 2, 2, NULL),
('3ML003DC', 'Lịch sử Việt Nam', 2, 2, NULL),
('3ML005DC', 'Chủ nghĩa xã hội khoa học', 2, 2, NULL),
('3ML006DC', 'Lịch sử Đảng CSVN', 3, 2, NULL),
('3ML007DC', 'Triết học Mác - Lênin', 3, 1, NULL),
('3ML008DC', 'Kinh tế chính trị Mác – Lênin', 2, 2, NULL),
('3NN003DC', 'Tiếng Anh chuyên ngành tin học', 2, 4, NULL),
('3NN011DH', 'Tiếng Anh chuyên ngành cơ điện tử', 2, 5, NULL),
('3TN002DH', 'Toán rời rạc', 2, 2, 'Kỹ thuật phần mềm'),
('3TN017DH', 'Trí tuệ nhân tạo', 2, 7, 'Công nghệ kỹ thuật máy tính'),
('3TN102CD', 'Bảo mật thông tin', 2, 5, 'Công nghệ thông tin'),
('3TN103CD', 'Cơ sở dữ liệu', 3, 3, 'Công nghệ thông tin'),
('3TN104CD', 'Lập trình Hướng đối tượng', 3, 3, 'Kỹ thuật phần mềm'),
('3TN105CD', 'Phân tích thiết kế hệ thống với UML', 2, 4, 'Công nghệ kỹ thuật máy tính'),
('3TN106CD', 'Cấu trúc dữ liệu và giải thuật', 3, 2, 'Công nghệ thông tin'),
('3TN109CD', 'Cấu trúc máy tính', 2, 2, 'Công nghệ thông tin'),
('3TN110DH', 'Điện toán đám mây', 3, 6, 'Công nghệ thông tin'),
('3TN116DH', 'Mẫu thiết kế cho phần mềm', 2, 6, 'Công nghệ thông tin'),
('3TN118DH', 'Khai phá dữ liệu', 3, 5, 'Công nghệ thông tin'),
('3TN137DH', 'Xử lý ảnh số', 2, 7, 'Công nghệ kỹ thuật máy tính'),
('3TN139DH', 'An ninh máy tính và mạng', 2, 7, 'Công nghệ thông tin'),
('3TN201CD', 'Lắp ráp và cài đặt máy tính', 2, 2, 'Công nghệ thông tin'),
('3TN207CD', 'Hệ điều hành Windows Server', 2, 6, 'Công nghệ thông tin'),
('3TN208CD', 'Hệ điều hành Linux', 2, 6, 'Công nghệ thông tin'),
('3TQ203DC', 'Quân sự chung', 1, 2, NULL),
('3TQ204DC', 'Kỹ thuật chiến đấu bộ binh và chiến thuật', 2, 2, NULL),
('4CK135DH', 'Vẽ kỹ thuật', 2, 2, 'Công nghệ kỹ thuật máy tính'),
('4CK197DH', 'Chuyên đề tốt nghiệp 1', 3, 8, 'Công nghệ kỹ thuật máy tính'),
('4CK198DH', 'Chuyên đề tốt nghiệp 2', 3, 8, 'Công nghệ kỹ thuật máy tính'),
('4CK199DH', 'Chuyên đề tốt nghiệp 3', 4, 8, 'Công nghệ kỹ thuật máy tính'),
('4CK210CD', 'Vận hành - Gia công trên máy CNC', 2, 6, 'Công nghệ kỹ thuật máy tính'),
('4CK282DH', 'Thiết kế, lắp mạch thủy lực khí nén', 2, 8, 'Công nghệ kỹ thuật máy tính'),
('4CK284DH', 'Thực tập sản xuất tại doanh nghiệp', 8, 7, 'Công nghệ thông tin'),
('4CK285DH', 'Thực tập tốt nghiệp', 8, 7, 'Công nghệ thông tin'),
('4DC001CS', 'Vật lý bổ trợ', 3, 1, NULL),
('4DC002CS', 'Hóa học bổ trợ', 2, 1, NULL),
('4DC016DC', 'Vật lý đại cương 1', 2, 1, NULL),
('4DC017DC', 'Vật lý đại cương 2', 2, 3, NULL),
('4DN131DH', 'Kỹ thuật điện - điện tử', 3, 3, 'Công nghệ thông tin'),
('4DN140DH', 'Robot công nghiệp', 2, 7, 'Công nghệ kỹ thuật máy tính'),
('4DN154DH', 'Điều khiển logic', 2, 6, 'Công nghệ kỹ thuật máy tính'),
('4DT104DH', 'Điện tử Số', 3, 3, 'Công nghệ kỹ thuật máy tính'),
('4DT136DH', 'Nhập môn ngành Kỹ thuật Máy tính', 3, 1, 'Công nghệ kỹ thuật máy tính'),
('4DT139DH', 'Ứng dụng Internet of Things', 2, 6, 'Công nghệ kỹ thuật máy tính'),
('4DT140DH', 'Lý thuyết mạch hệ thống', 2, 2, 'Công nghệ kỹ thuật máy tính'),
('4DT239DH', 'Thiết kế hệ thống mạng', 2, 6, 'Công nghệ kỹ thuật máy tính'),
('4DT241DH', 'Lập trình Web ứng dụng', 2, 6, 'Công nghệ kỹ thuật máy tính'),
('4DT308DH', 'Đồ án Vi điều khiển', 1, 4, 'Công nghệ kỹ thuật máy tính'),
('4DT333DH', 'Đồ án Hệ thống nhúng', 1, 6, 'Công nghệ kỹ thuật máy tính'),
('4NN001DC', 'Tiếng Anh cơ bản 1', 3, 1, NULL),
('4NN002DC', 'Tiếng Anh cơ bản 2', 3, 2, NULL),
('4NN009DC', 'Tiếng Anh cơ bản 3', 3, 3, NULL),
('4SP001DC', 'Tiếng việt nâng cao 1', 3, 1, NULL),
('4SP002DC', 'Tiếng Việt nâng cao 2', 3, 2, NULL),
('4SP004DC', 'Tiếng Việt nâng cao 3', 3, 3, NULL),
('4SP103DC', 'Kỹ năng mềm', 3, 3, NULL),
('4TN003CD', 'Java cơ bản', 2, 3, 'Công nghệ thông tin'),
('4TN004DH', 'Quản trị dự án', 2, 5, 'Công nghệ thông tin'),
('4TN103CD', 'Lập trình C/C++', 2, 1, 'Công nghệ thông tin'),
('4TN105CD', 'Mạng máy tính', 3, 4, 'Kỹ thuật phần mềm'),
('4TN105DH', 'Lập trình Windows', 2, 3, 'Công nghệ thông tin'),
('4TN106DH', 'Cơ sở lập trình Web', 2, 3, 'Công nghệ thông tin'),
('4TN107CD', 'Lập trình Web PHP', 3, 4, 'Kỹ thuật phần mềm'),
('4TN111DH', 'Lập trình di động', 2, 4, 'Công nghệ thông tin'),
('4TN115DH', 'Kỹ thuật lập trình nhúng', 2, 6, 'Công nghệ thông tin'),
('4TN117DH', 'Thiết kế đa phương tiện', 2, 6, 'Công nghệ thông tin'),
('4TN119DH', 'Kiểm thử phần mềm', 2, 6, 'Công nghệ thông tin'),
('4TN120DH', 'Lập trình nâng cao', 2, 4, 'Công nghệ thông tin'),
('4TN121DH', 'Công nghệ phần mềm', 2, 5, 'Công nghệ thông tin'),
('4TN122DH', 'Xử lý dữ liệu lớn', 2, 6, 'Công nghệ thông tin'),
('4TN123DH', 'Nhập môn ngành Công nghệ thông tin', 3, 1, 'Công nghệ thông tin'),
('4TN124DH', 'Trí tuệ nhân tạo nâng cao', 2, 6, 'Công nghệ thông tin'),
('4TN125DH', 'Ứng dụng AI trong công nghệ thông tin', 2, 6, 'Công nghệ thông tin'),
('4TN126DH', 'Lập trình Game', 2, 6, 'Công nghệ thông tin'),
('4TN127DH', 'Chuyên đề tốt nghiệp 1', 3, 8, 'Công nghệ thông tin'),
('4TN128DH', 'Chuyên đề tốt nghiệp 2', 3, 8, 'Công nghệ thông tin'),
('4TN129DH', 'Chuyên đề tốt nghiệp 3', 4, 8, 'Công nghệ thông tin'),
('4TN133DH', 'Kỹ thuật ghép nối máy tính', 3, 6, 'Công nghệ kỹ thuật máy tính'),
('4TN203DC', 'Phát triển ứng dụng Web 1', 4, 4, 'Công nghệ thông tin'),
('4TN204CD', 'Phát triển ứng dụng di động 1', 4, 4, 'Công nghệ thông tin'),
('4TN204DH', 'Phát triển ứng dụng di động 2', 2, 5, 'Công nghệ thông tin'),
('4TN205CD', 'Hệ quản trị cơ sở dữ liệu SQL Server', 2, 5, 'Công nghệ thông tin'),
('4TN206CD', 'Hệ quản trị cơ sở dữ liệu MySQL', 2, 5, 'Công nghệ thông tin'),
('4TN209CD', 'Phát triển ứng dụng Windows 1', 4, 4, 'Công nghệ thông tin'),
('4TN219DH', 'Quản trị mạng', 2, 6, 'Công nghệ thông tin'),
('4TN221DH', 'Phát triển ứng dụng Windows 2', 2, 5, 'Công nghệ thông tin'),
('4TN222DH', 'Phát triển ứng dụng Web 2', 2, 5, 'Công nghệ thông tin'),
('4TN301DH', 'Đồ án phát triển ứng dụng Web', 1, 5, 'Công nghệ thông tin'),
('4TN302DH', 'Đồ án phát triển ứng dụng di động', 1, 5, 'Công nghệ thông tin'),
('4TN305DH', 'Tin học cơ bản', 3, 1, 'Kỹ thuật phần mềm'),
('4TQ009DC', 'Điền kinh 1', 1, 1, NULL),
('4TQ010DC', 'Điền kinh 2', 1, 2, NULL),
('4TQ011DC', 'Điền kinh 3', 1, 3, NULL),
('4TQ012DC', 'Bóng chuyền 1', 1, 2, NULL),
('4TQ013DC', 'Bóng chuyền 2', 1, 3, NULL),
('4TQ014DC', 'Bóng đá 1', 1, 2, NULL),
('4TQ015DC', 'Bóng đá 2', 1, 3, NULL),
('4TQ016DC', 'Bóng rổ 1', 1, 2, NULL),
('4TQ017DC', 'Bóng rổ 2', 1, 3, NULL),
('5DT235DH', 'Thực tập sản xuất tại doanh nghiệp', 8, 7, 'Công nghệ kỹ thuật máy tính'),
('5DT236DH', 'Thực tập tốt nghiệp', 8, 8, 'Công nghệ kỹ thuật máy tính'),
('5DT337DH', 'Đồ án tốt nghiệp', 10, 8, 'Công nghệ kỹ thuật máy tính'),
('5TN207DH', 'Thực tập sản xuất tại doanh nghiệp', 8, 7, 'Kỹ thuật phần mềm'),
('5TN208DH', 'Thực tập tốt nghiệp', 8, 7, 'Kỹ thuật phần mềm'),
('5TN303DH', 'Đồ án tốt nghiệp', 10, 8, 'Công nghệ thông tin');

-- --------------------------------------------------------

--
-- Table structure for table `lop`
--

CREATE TABLE `lop` (
  `MaLop` varchar(20) NOT NULL,
  `TenLop` varchar(100) NOT NULL,
  `SiSo` int(11) NOT NULL DEFAULT 0,
  `GioiHanSinhVien` int(11) DEFAULT NULL,
  `NganhHoc` varchar(100) DEFAULT NULL,
  `KhoaHoc` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lop`
--

INSERT INTO `lop` (`MaLop`, `TenLop`, `SiSo`, `GioiHanSinhVien`, `NganhHoc`, `KhoaHoc`) VALUES
('DHCNKTMK19A1', 'Đại học Công nghệ kỹ thuật máy tính Khóa 19 A1', 0, NULL, 'Công nghệ kỹ thuật máy tính', 'K19'),
('DHCNKTMK19A2', 'Đại học Công nghệ kỹ thuật máy tính Khóa 19 A2', 0, NULL, 'Công nghệ kỹ thuật máy tính', 'K19'),
('DHCNKTMK20A1', 'Đại học Công nghệ kỹ thuật máy tính Khóa 20 A1', 0, NULL, 'Công nghệ kỹ thuật máy tính', 'K20'),
('DHCNKTMK20A2', 'Đại học Công nghệ kỹ thuật máy tính Khóa 20 A2', 0, NULL, 'Công nghệ kỹ thuật máy tính', 'K20'),
('DHCNKTMK20A3', 'Đại học Công nghệ kỹ thuật máy tính Khóa 20 A3', 0, NULL, 'Công nghệ kỹ thuật máy tính', 'K20'),
('DHCNKTMK21A1', 'Đại học Công nghệ kỹ thuật máy tính Khóa 21 A1', 0, NULL, 'Công nghệ kỹ thuật máy tính', 'K21'),
('DHCNKTMK21A2', 'Đại học Công nghệ kỹ thuật máy tính Khóa 21 A2', 0, NULL, 'Công nghệ kỹ thuật máy tính', 'K21'),
('DHCNKTMK21A3', 'Đại học Công nghệ kỹ thuật máy tính Khóa 21 A3', 0, NULL, 'Công nghệ kỹ thuật máy tính', 'K21'),
('DHCNKTMK22A1', 'Đại học Công nghệ kỹ thuật máy tính Khóa 22 A1', 0, NULL, 'Công nghệ kỹ thuật máy tính', 'K22'),
('DHCNKTMK22A2', 'Đại học Công nghệ kỹ thuật máy tính Khóa 22 A2', 0, NULL, 'Công nghệ kỹ thuật máy tính', 'K22'),
('DHCNKTMK22A3', 'Đại học Công nghệ kỹ thuật máy tính Khóa 22 A3', 10, NULL, 'Công nghệ kỹ thuật máy tính', 'K22'),
('DHCTTCK19A1', 'Đại học Công nghệ thông tin Khóa 19 A1', 0, NULL, 'Công nghệ thông tin', 'K19'),
('DHCTTCK19A2', 'Đại học Công nghệ thông tin Khóa 19 A2', 0, NULL, 'Công nghệ thông tin', 'K19'),
('DHCTTCK20A1', 'Đại học Công nghệ thông tin Khóa 20 A1', 0, NULL, 'Công nghệ thông tin', NULL),
('DHCTTCK20A2', 'Đại học Công nghệ thông tin Khóa 20 A2', 0, NULL, 'Công nghệ thông tin', NULL),
('DHCTTCK20A3', 'Đại học Công nghệ thông tin Khóa 20 A3', 0, NULL, 'Công nghệ thông tin', NULL),
('DHCTTCK20A4', 'Đại học Công nghệ thông tin Khóa 20 A4', 0, NULL, 'Công nghệ thông tin', 'K20'),
('DHCTTCK21A1', 'Đại học Công nghệ thông tin Khóa 21 A1', 0, NULL, 'Công nghệ thông tin', NULL),
('DHCTTCK21A2', 'Đại học Công nghệ thông tin Khóa 21 A2', 0, NULL, 'Công nghệ thông tin', NULL),
('DHCTTCK21A3', 'Đại học Công nghệ thông tin Khóa 21 A3', 0, NULL, 'Công nghệ thông tin', 'K21'),
('DHCTTCK22A1', 'Đại học Công nghệ thông tin Khóa 22 A1', 0, NULL, 'Công nghệ thông tin', 'K22'),
('DHCTTCK22A2', 'Đại học Công nghệ thông tin Khóa 22 A2', 0, NULL, 'Công nghệ thông tin', 'K22'),
('DHCTTCK22A3', 'Đại học Công nghệ thông tin Khóa 22 A3', 10, NULL, 'Công nghệ thông tin', 'K22'),
('DHKTPMK20A1', 'Đại học Kỹ thuật phần mềm Khóa 20 A1', 0, NULL, 'Kỹ thuật phần mềm', NULL),
('DHKTPMK20A2', 'Đại học Kỹ thuật phần mềm Khóa 20 A2', 0, NULL, 'Kỹ thuật phần mềm', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `phanlop`
--

CREATE TABLE `phanlop` (
  `mahp` char(15) NOT NULL,
  `malop` char(15) NOT NULL,
  `giangvien` varchar(50) NOT NULL,
  `lophocphan` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `phanlop`
--

INSERT INTO `phanlop` (`mahp`, `malop`, `giangvien`, `lophocphan`) VALUES
('2DC007DC', 'DHCNKTMK22A3', 'ThS. Trần Đức Thắng', 'Xác suất thống kê/DHCNKTMK22A3'),
('2DC011DC', 'DHCNKTMK22A3', 'ThS. Trần Đức Thắng', 'Hóa học đại cương 1/DHCNKTMK22A3'),
('2ML002DC', 'DHCNKTMK22A3', 'ThS. Trần Đức Thắng', 'Tư tưởng Hồ Chí Minh/DHCNKTMK22A3'),
('3CK201CD', 'DHCNKTMK22A3', 'ThS. Trần Đức Thắng', 'Gia công nguội - Hàn cơ bản/DHCNKTMK22A3'),
('3DC005DC', 'DHCNKTMK22A3', 'ThS. Trần Đức Thắng', 'Toán cao cấp 2/DHCNKTMK22A3'),
('3DT103DH', 'DHCNKTMK22A3', 'ThS. Trần Đức Thắng', 'Điện tử tương tự/DHCNKTMK22A3');

-- --------------------------------------------------------

--
-- Table structure for table `sinhvien`
--

CREATE TABLE `sinhvien` (
  `MaSV` varchar(20) NOT NULL,
  `MaLop` varchar(20) NOT NULL,
  `HoTen` varchar(100) NOT NULL,
  `GioiTinh` enum('Nam','Nữ','Khác') NOT NULL DEFAULT 'Khác',
  `NgaySinh` date DEFAULT NULL,
  `QueQuan` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sinhvien`
--

INSERT INTO `sinhvien` (`MaSV`, `MaLop`, `HoTen`, `GioiTinh`, `NgaySinh`, `QueQuan`) VALUES
('SV1', 'DHCNKTMK22A3', 'Nguyễn Văn An', 'Nam', '2003-03-10', 'Hà Nội'),
('SV10', 'DHCNKTMK22A3', 'Đỗ Thị Liên', 'Nữ', '2003-08-19', 'Cà Mau'),
('SV11', 'DHCNKTMK22A3', 'Khổng Văn Lâm', 'Nam', '2003-10-25', 'Bình Thuận'),
('SV12', 'DHCNKTMK22A3', 'Nguyễn Thị Miễu', 'Nữ', '2004-01-03', 'An Giã'),
('SV13', 'DHCNKTMK22A3', 'Phan Văn Nam', 'Nam', '2003-06-14', 'Tây Ninh'),
('SV14', 'DHCNKTMK22A3', 'Vũ Thị Oanh', 'Nữ', '2004-03-27', 'Bình Dương'),
('SV15', 'DHCNKTMK22A3', 'Lý Văn Phú', 'Nam', '2003-07-09', 'Bình Thuận'),
('SV16', 'DHCNKTMK22A3', 'Ngô Thị Hồng', 'Nữ', '2003-09-16', 'Kiên Giang'),
('SV17', 'DHCNKTMK22A3', 'Sô Văn Quý', 'Nam', '2003-12-01', 'Hà Nội'),
('SV18', 'DHCNKTMK22A3', 'Lê Thị Mỹ', 'Nữ', '2004-05-11', 'Hồ Chí Minh'),
('SV19', 'DHCTTCK22A3', 'Trọng Anh Vũ', 'Nam', '2003-08-22', 'Lâm Đồng'),
('SV2', 'DHCTTCK22A3', 'Trần Thị Bình', 'Nữ', '2003-07-15', 'Nghệ An'),
('SV20', 'DHCTTCK22A3', 'Bùi Thị Nho', 'Nữ', '2003-11-30', 'An Giang'),
('SV3', 'DHCTTCK22A3', 'Lê Văn Châu', 'Nam', '2003-09-22', 'Đà Nẵng'),
('SV4', 'DHCTTCK22A3', 'Phạm Thị Duyên', 'Nữ', '2003-11-05', 'Quảng Nam'),
('SV5', 'DHCTTCK22A3', 'Hoàng Văn Em', 'Nam', '2003-12-18', 'Thừa Thiên Huế'),
('SV6', 'DHCTTCK22A3', 'Bùi Thị Phương', 'Nữ', '2004-02-28', 'Khánh Hòa'),
('SV7', 'DHCTTCK22A3', 'Ngô Văn Giang', 'Nam', '2003-04-12', 'Bình Định'),
('SV8', 'DHCTTCK22A3', 'Vũ Thị Hải', 'Nữ', '2003-05-30', 'Phú Yên'),
('SV9', 'DHCTTCK22A3', 'Đặng Văn Khiêm', 'Nam', '2003-06-08', 'Kiên Giang');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `ten` varchar(50) NOT NULL,
  `TenGV` varchar(100) DEFAULT NULL,
  `matkhau` varchar(255) NOT NULL,
  `quyen` enum('admin','congtac','daotao','khaothi','sinhvien','giangvien','guest') NOT NULL DEFAULT 'guest',
  `MaSV` varchar(20) DEFAULT NULL,
  `role_expiry` datetime DEFAULT NULL,
  `extra_perms` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `ten`, `TenGV`, `matkhau`, `quyen`, `MaSV`, `role_expiry`, `extra_perms`) VALUES
(1, 'admin', NULL, '$2y$10$CNpzFPA24083RxT/MkIlceyDRLc2jGcrW15USr3XeWImd7YgziuIO', 'admin', NULL, NULL, NULL),
(2, 'congtac', NULL, '$2y$10$hz1.I3P09GYmAPTAgQI/D.hfC1eNOgQNpmqV6SMsyJZI1jSJCyz66', 'congtac', NULL, NULL, '{\"sua_sinhvien\",\"xoa_sinhvien\"}'),
(3, 'daotao', NULL, '$2y$10$8OdvJXCd3NDXsR0nthAlHe5mue4ko1pkF59YQPBSYeVLLtUdyrGv6', 'daotao', NULL, NULL, NULL),
(4, 'khaothi', NULL, '$2y$10$sCGx52lQNmUr7VCuUYMTiOpc1ZBRnQfPSkeADths1UOT364EocfZq', 'khaothi', NULL, NULL, NULL),
(14, 'SV1', NULL, '$2y$10$g4zWFWHAKO.N7CvSCX7rfOE4KjllKwfIpAdauuusy06KRGcN/USfa', 'sinhvien', 'SV1', NULL, NULL),
(22, 'gv1', 'ThS. Trần Đức Thắng', '$2y$10$M6ee2dMz9OCgHNaDooDC3.jBFcRuxQOzm02Ba0krNcISGnA/MqZda', 'giangvien', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `diem`
--
ALTER TABLE `diem`
  ADD PRIMARY KEY (`MaSV`,`MaHP`),
  ADD KEY `MaHP` (`MaHP`);

--
-- Indexes for table `hocphan`
--
ALTER TABLE `hocphan`
  ADD PRIMARY KEY (`MaHP`);

--
-- Indexes for table `lop`
--
ALTER TABLE `lop`
  ADD PRIMARY KEY (`MaLop`);

--
-- Indexes for table `phanlop`
--
ALTER TABLE `phanlop`
  ADD PRIMARY KEY (`mahp`,`malop`,`giangvien`);

--
-- Indexes for table `sinhvien`
--
ALTER TABLE `sinhvien`
  ADD PRIMARY KEY (`MaSV`),
  ADD KEY `MaLop` (`MaLop`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ten` (`ten`),
  ADD UNIQUE KEY `MaSV` (`MaSV`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `diem`
--
ALTER TABLE `diem`
  ADD CONSTRAINT `diem_ibfk_1` FOREIGN KEY (`MaSV`) REFERENCES `sinhvien` (`MaSV`),
  ADD CONSTRAINT `diem_ibfk_2` FOREIGN KEY (`MaHP`) REFERENCES `hocphan` (`MaHP`);

--
-- Constraints for table `sinhvien`
--
ALTER TABLE `sinhvien`
  ADD CONSTRAINT `sinhvien_ibfk_1` FOREIGN KEY (`MaLop`) REFERENCES `lop` (`MaLop`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
