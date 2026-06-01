-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 07, 2026 at 08:44 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dvsdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `action_logs`
--

CREATE TABLE `action_logs` (
  `id` int(10) NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `document_title` varchar(1000) DEFAULT NULL,
  `document_desc` varchar(1000) DEFAULT NULL,
  `document_receive_type` varchar(255) DEFAULT NULL,
  `document_type` varchar(255) DEFAULT NULL,
  `no_pages` varchar(40) DEFAULT NULL,
  `document_receiver` varchar(255) DEFAULT NULL,
  `document_sender` varchar(255) DEFAULT NULL,
  `document_date` varchar(255) DEFAULT NULL,
  `remarks` varchar(2000) DEFAULT 'N/A',
  `datetime_action` datetime NOT NULL,
  `action` varchar(40) DEFAULT 'N/A',
  `action_from` varchar(255) DEFAULT NULL,
  `action_by` varchar(255) DEFAULT NULL,
  `action_by_from` varchar(255) DEFAULT NULL,
  `encoded_by` varchar(255) DEFAULT NULL,
  `office_from` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ada_signatory_options`
--

CREATE TABLE `ada_signatory_options` (
  `id` int(11) NOT NULL,
  `option_type` varchar(64) NOT NULL,
  `option_value` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ada_signatory_options`
--

INSERT INTO `ada_signatory_options` (`id`, `option_type`, `option_value`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'certified_correct', 'NATHALLIE D. BALEÑA', 1, 1, '2026-03-10 07:30:46', '2026-03-10 07:31:09'),
(2, 'certified_correct', 'ERIC P. LAGUNZAD', 1, 2, '2026-03-10 07:31:25', NULL),
(3, 'approved_by', 'LEA O. TORRES', 1, 1, '2026-03-10 07:31:31', NULL),
(4, 'approved_by', 'AMOR A. ROBREDILLO', 1, 2, '2026-03-10 07:31:35', NULL),
(5, 'agency_authorized_signatory', 'ANTONIETTE C. DE LOS SANTOS', 1, 1, '2026-03-10 07:31:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `employee_name` varchar(255) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `request_uri` varchar(500) DEFAULT NULL,
  `additional_data` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `designation_limit`
--

CREATE TABLE `designation_limit` (
  `id` int(10) NOT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `designated_udc` varchar(255) DEFAULT NULL,
  `designated_office` varchar(10000) DEFAULT NULL,
  `current_designated` int(10) DEFAULT NULL,
  `max_designated` int(10) DEFAULT NULL,
  `visibility` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `designation_limit`
--

INSERT INTO `designation_limit` (`id`, `designation`, `designated_udc`, `designated_office`, `current_designated`, `max_designated`, `visibility`) VALUES
(1, 'Records Unit', 'L5Vbk,fUpF9,6V7ro,gHfmt', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 3, 999, 1),
(2, 'Records Officer', 'L5Vbk,6V7ro,gHfmt', 'DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 3, 10, 1),
(3, 'Office of the PENRO', 'e1fja', 'DENR-PENRO EASTERN SAMAR', 1, 999, 1),
(4, 'PENR Officer', 'rMu7j', 'DENR-PENRO EASTERN SAMAR', 1, 1, 1),
(5, 'Officer-In-Charge (PENR Office)', '', '', 0, 2, 1),
(6, 'Management Services Division', 'jnpsn', 'DENR-PENRO EASTERN SAMAR', 1, 999, 1),
(7, 'Management Services Division Chief', '20r83', 'DENR-PENRO EASTERN SAMAR', 1, 999, 1),
(8, 'Technical Services Division', 'UhI7N', 'DENR-PENRO EASTERN SAMAR', 1, 999, 1),
(9, 'Technical Services Division Chief', 'fTGII', 'DENR-PENRO EASTERN SAMAR', 1, 999, 1),
(10, 'Planning Section', 'qvPnB,phem1', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR', 2, 999, 1),
(11, 'Planning Section Chief', 'phem1', 'DENR-PENRO EASTERN SAMAR', 1, 999, 1),
(12, 'Admin and Finance Section', 'jnpsn', 'DENR-PENRO EASTERN SAMAR', 1, 999, 1),
(13, 'ICT Unit', 'GhsCX,VzrKr,myrsb', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,None', 3, 999, 1),
(14, 'Budget Unit', 'MsBfy,haV0Q,Jp4Nb', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR', 3, 999, 1),
(15, 'Budget Officer', 'Jp4Nb', 'DENR-PENRO EASTERN SAMAR', 1, 999, 1),
(16, 'Accounting Unit', 'vqliT,4HyLy,7Ci0W,20r83', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR', 4, 999, 1),
(17, 'Accountant III', 'qs7lT', 'None', 1, 999, 1),
(18, 'Personnel & General Services Unit', 'AoKK3', 'DENR-PENRO EASTERN SAMAR', 1, 999, 1),
(19, 'HR', 'boURR', 'DENR-PENRO EASTERN SAMAR', 1, 999, 1),
(20, 'Cashiers Unit', 'nQNYv,exh1u,HQvui', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR', 3, 999, 1),
(21, 'Cashier', 'HQvui', 'DENR-PENRO EASTERN SAMAR', 1, 999, 1),
(22, 'Procurement & Supply Unit', 'pywCX,ARhbj,DsSN2', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR', 3, 999, 1),
(23, 'Supply Officer I', 'DsSN2,nenmw', 'DENR-PENRO EASTERN SAMAR,CENRO BORONGAN', 2, 999, 1),
(24, 'Regulation & Permitting Section', 'aI37k,gQtpt,RXp4F,iMi2u', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 4, 999, 1),
(25, 'Regulation & Permitting Section Chief', 'gQtpt,RXp4F,iMi2u', 'DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 3, 999, 1),
(26, 'Conservation & Development Section', 'w8lTV,JjObD,MnUOc,DeACB', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 4, 999, 1),
(27, 'Conservation & Development Section Chief', 'JjObD,MnUOc,DeACB', 'DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 3, 999, 1),
(28, 'Monitoring & Enforcement Section', '4TCk3,6pOcf,gnM6b,GTpUT', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 4, 999, 1),
(29, 'Monitoring & Enforcement Section Chief', '6pOcf,gnM6b,GTpUT', 'DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 3, 999, 1),
(30, 'ENGP Focal Person', 'w8lTV,OFBzP', 'DENR-PENRO EASTERN SAMAR,CENRO DOLORES', 2, 10, 1),
(31, 'PASu - GMRPLS', 'yDYxY', 'DENR-PENRO EASTERN SAMAR', 1, 99, 1),
(32, 'ADR Focal Person', 'aI37k,YNCXO', 'DENR-PENRO EASTERN SAMAR,CENRO BORONGAN', 2, 99, 1),
(33, 'GAD Focal Person', 'Jp4Nb,lf6O7,pOyBY', 'DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 3, 10, 1),
(34, 'Senior Citizen & PWD Focal Person', 'Jp4Nb,lf6O7,Qrcd1', 'DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 3, 10, 1),
(35, 'Youth Desk Officer', '', '', 0, 1, 1),
(36, 'Information Officer', 'VzrKr,yIBVl,pOyBY', 'DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 3, 99, 1),
(37, '8888 Focal Person', 'VzrKr,DeACB', 'DENR-PENRO EASTERN SAMAR,CENRO BORONGAN', 2, 1, 1),
(38, 'CSS Focal Person', 'phem1,Tsd8Q,3JFRa', 'DENR-PENRO EASTERN SAMAR,CENRO DOLORES,CENRO BORONGAN', 3, 10, 1),
(39, 'SPICS Focal Person', 'phem1', 'DENR-PENRO EASTERN SAMAR', 1, 1, 1),
(40, 'Citizens Charter Focal Person', 'phem1', 'DENR-PENRO EASTERN SAMAR', 1, 1, 1),
(41, 'GSIS AAO Focal Person', 'VzrKr', 'DENR-PENRO EASTERN SAMAR', 1, 2, 1),
(42, 'HDMF (Pagibig Fund) Focal Person', 'AoKK3', 'DENR-PENRO EASTERN SAMAR', 1, 1, 1),
(44, 'ICU', 'g1ii6', 'DENR-PENRO EASTERN SAMAR', 1, 1, 3),
(45, 'CENRO Officer', 'AUzH7,vXcVj', 'CENRO DOLORES,CENRO BORONGAN', 2, 999, 1),
(46, 'Office of the CENRO', 'qBTBa,9tqJw', 'CENRO DOLORES,CENRO BORONGAN', 2, 999, 1),
(47, 'EMB Focal Person', 'OFBzP', 'CENRO DOLORES', 1, 1, 1),
(48, 'MGB Focal Person', 'H3E2K', 'CENRO DOLORES', 1, 1, 1),
(49, 'Legal Researcher', 'iDP3J', 'CENRO DOLORES', 1, 999, 1),
(50, 'Property Custodian', 'gnM6b', 'CENRO DOLORES', 1, 999, 1),
(51, 'Reforestation / Afforestation Unit', 'OFBzP,Aii7Y', 'CENRO DOLORES,CENRO DOLORES', 2, 999, 1),
(52, 'CBFM Unit', 'EwVJy,ThBRz,fNHDY,OMWMb,4WAB7,yIBVl,rxNqS', 'CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO BORONGAN', 7, 999, 1),
(53, 'Biodiversity Conservation Unit', 'H3E2K,aijjr', 'CENRO DOLORES,CENRO DOLORES', 2, 999, 1),
(54, 'Forest Protection and Law Enforcement Unit', 'mfEhG,MpAVl,4JF3R,QS6dy,hIpUt,cnwIc,OjVEg,HMZJP,kiP9D', 'CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO BORONGAN', 9, 999, 1),
(55, 'LAWIN', 'qCZts,Zm4fj,RRerh,yIBVl,mYQ0w', 'CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO BORONGAN', 5, 999, 1),
(56, 'Forest Licensing / Utilization Unit', 'Zm4fj,RRerh,4JF3R', 'CENRO DOLORES,CENRO DOLORES,CENRO DOLORES', 3, 999, 1),
(57, 'Surveys and Mapping', 'ZLJgs,Seuvp', 'CENRO DOLORES,CENRO DOLORES', 2, 999, 1),
(58, 'Patents and Deeds', 'miJlS,Seuvp,lf6O7,wh6XV,Tsd8Q,5C5So,EWFEi,FxSOZ', 'CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES,CENRO DOLORES', 8, 999, 1),
(59, 'Planning Unit', 'WwDyZ,20HBd', 'CENRO DOLORES,CENRO DOLORES', 2, 999, 1),
(60, 'Planning Unit Chief', 'WwDyZ', 'CENRO DOLORES', 1, 999, 1),
(61, 'Admin Unit', 'MnUOc', 'CENRO DOLORES', 1, 999, 1),
(62, 'SWIS Focal Person', 'yIBVl', 'CENRO DOLORES', 1, 10, 1),
(63, 'CBFM Focal Person', 'MnUOc', 'CENRO DOLORES', 1, 10, 1),
(64, 'Cave Focal Person', 'H3E2K', 'CENRO DOLORES', 1, 10, 1),
(65, 'Nursery Focal Person', 'aijjr', 'CENRO DOLORES', 1, 10, 1),
(66, 'LAWIN Focal Person', 'yIBVl', 'CENRO DOLORES', 1, 10, 1),
(67, 'FLUP Focal Person', 'Zm4fj', 'CENRO DOLORES', 1, 10, 1),
(68, 'System Admin', 'GhsCX,WPeLS,VzrKr', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR', 4, 10, 1),
(69, 'Processor', '4HyLy,s1JxV,LD6Eo,YS9M3', 'DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR,DENR-PENRO EASTERN SAMAR', 4, 999, 1),
(70, 'Receiving & Releasing Officer', 'ELZf7', 'CENRO BORONGAN', 1, 99, 1),
(71, 'Interim Admin', '3JFRa', 'CENRO BORONGAN', 1, 99, 1),
(72, 'DMO IV', 'JhtgI', 'CENRO BORONGAN', 1, 99, 1),
(73, 'Interim Planning Officer', 'pOyBY', 'CENRO BORONGAN', 1, 99, 1),
(74, 'Sr. EMS', 'Xa7q4', 'CENRO BORONGAN', 1, 99, 5),
(75, 'EMS II', 'DeACB', 'CENRO BORONGAN', 1, 99, 5),
(76, 'Forester III', '', '', 0, 99, 5),
(77, 'DMO III', '', '', 0, 99, 5),
(78, 'Forest Ranger', 'XmVRC', 'CENRO BORONGAN', 1, 99, 5),
(79, 'EMS I', '4QjYe', 'CENRO BORONGAN', 1, 99, 3),
(80, 'LMO IV', 'IzoJD', 'CENRO BORONGAN', 1, 99, 5),
(81, 'Engineer III', 'xfuhe', 'CENRO BORONGAN', 1, 99, 5),
(82, 'Forester II', 'GTpUT,e5wje,kiP9D', 'CENRO BORONGAN,CENRO BORONGAN,CENRO BORONGAN', 3, 99, 5),
(83, 'Forest Technician I', 'mYQ0w', 'CENRO BORONGAN', 1, 99, 5),
(84, 'Forest Technician II', 'rxNqS', 'CENRO BORONGAN', 1, 99, 5),
(85, 'CBFM Coordinator', 'rxNqS', 'CENRO BORONGAN', 1, 99, 1),
(86, 'Forester I', 'ryn2T', 'CENRO BORONGAN', 1, 99, 5),
(87, 'LMO III', 'iMi2u', 'CENRO BORONGAN', 1, 99, 5),
(88, 'Guiuan Marine Resource Protected Landscape & Seascape', 'Xa7q4', 'CENRO BORONGAN', 1, 99, 1),
(89, 'Enhanced National Greening Program Unit', 'ryn2T', 'CENRO BORONGAN', 1, 99, 1),
(90, 'Community Based Forest Management Unit', 'rxNqS', 'CENRO BORONGAN', 1, 99, 1),
(91, 'Inland Wetland and Wildlife Management Unit', 'e5wje', 'CENRO BORONGAN', 1, 99, 1),
(92, 'Survey Unit', 'xfuhe', 'CENRO BORONGAN', 1, 99, 1),
(93, 'Land Management Unit', 'IzoJD', 'CENRO BORONGAN', 1, 99, 1),
(94, 'Forest Utilization and Permitting Unit', '4QjYe', 'CENRO BORONGAN', 1, 99, 1),
(95, 'Balangiga Sub-station', 'XmVRC', 'CENRO BORONGAN', 1, 99, 1),
(97, 'OpCen Focal Person', 'GTpUT', 'CENRO BORONGAN', 1, 99, 1),
(98, 'ARTA Focal Person', '3JFRa', 'CENRO BORONGAN', 1, 99, 1),
(99, 'Health and Wellness Focal Person', 'pOyBY', 'CENRO BORONGAN', 1, 99, 1),
(100, '8888 Alternate Focal Person', 'IzoJD', 'CENRO BORONGAN', 1, 99, 1),
(101, 'PET-CBFM Focal Person', '0KxKi', 'CENRO BORONGAN', 1, 99, 1),
(102, 'GIS & One Control Map Focal Person', 'xE4lw', 'CENRO BORONGAN', 1, 99, 1),
(103, 'GIS & One Control Map Alternate Focal Person', 'fgpJd', 'CENRO BORONGAN', 1, 99, 1),
(104, 'Quick Response Team Focal Person', 'GTpUT', 'CENRO BORONGAN', 1, 99, 1),
(105, 'Foreshore Area Management Unit', '0KxKi', 'CENRO BORONGAN', 1, 99, 1),
(106, 'EMB Personnel', 'iRtEl', 'CENRO BORONGAN', 1, 99, 1),
(107, 'Credit Officer', 'IzRFV', 'CENRO BORONGAN', 1, 99, 1),
(108, 'IT Focal Person', 'cGwtk', 'CENRO BORONGAN', 1, 99, 1),
(109, 'Liaison Officer', 'pOyBY', 'None,CENRO BORONGAN', 1, 99, 1);

-- --------------------------------------------------------

--
-- Table structure for table `dv_entries`
--

CREATE TABLE `dv_entries` (
  `id` int(10) UNSIGNED NOT NULL,
  `processing_no` varchar(255) NOT NULL,
  `dv_no` varchar(255) NOT NULL,
  `ada_check_no` varchar(255) NOT NULL,
  `ors_no` varchar(64) NOT NULL DEFAULT '',
  `payee` varchar(512) NOT NULL,
  `tin_employee_no` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(512) NOT NULL DEFAULT '',
  `amount` varchar(64) NOT NULL DEFAULT '',
  `voucher_type` varchar(255) NOT NULL,
  `voucher_date` varchar(255) NOT NULL,
  `particulars` text DEFAULT NULL,
  `datetime_encoded` varchar(64) NOT NULL DEFAULT '',
  `encoded_from` varchar(255) NOT NULL,
  `encoded_by` varchar(255) NOT NULL,
  `office_from` varchar(255) NOT NULL DEFAULT '',
  `coa_options` text DEFAULT NULL,
  `coa_category` varchar(255) DEFAULT NULL,
  `coa_subsection` varchar(255) DEFAULT NULL,
  `return_remarks` text DEFAULT NULL,
  `process_history` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encoded_voucher_no`
--

CREATE TABLE `encoded_voucher_no` (
  `id` int(10) NOT NULL,
  `dv_no` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `routing`
--

CREATE TABLE `routing` (
  `id` int(10) NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `document_title` varchar(1000) DEFAULT NULL,
  `document_desc` varchar(1000) DEFAULT NULL,
  `document_receive_type` varchar(255) DEFAULT NULL,
  `encoded_from` varchar(255) DEFAULT NULL,
  `encoded_by` varchar(255) DEFAULT NULL,
  `document_type` varchar(255) DEFAULT NULL,
  `no_pages` int(10) DEFAULT NULL,
  `document_receiver` varchar(255) DEFAULT NULL,
  `document_sender` varchar(255) DEFAULT NULL,
  `document_date` date DEFAULT NULL,
  `datetime_encoded` datetime NOT NULL DEFAULT current_timestamp(),
  `document_status` varchar(255) DEFAULT NULL,
  `priority` varchar(50) DEFAULT NULL,
  `remarks` varchar(1000) DEFAULT NULL,
  `purpose` varchar(255) NOT NULL DEFAULT 'N/A',
  `office_from` varchar(500) NOT NULL DEFAULT 'N/A',
  `for_action` enum('false','For Information','Appropriate Action','For Dissemination','Please Acknowledge','Please Monitor','Please Review and Affix Initial if in Order') DEFAULT 'false',
  `complexity` enum('None','Simple','Complex','Highly Technical') DEFAULT 'None',
  `file_name` varchar(500) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `system_name` varchar(255) NOT NULL DEFAULT 'PENRO Disbursement Voucher System',
  `page_title` varchar(255) NOT NULL DEFAULT 'PENRO Disbursement Voucher System',
  `company_name` varchar(255) NOT NULL DEFAULT 'Provincial Environment and Natural Resources Office',
  `browser_title` varchar(255) NOT NULL DEFAULT 'PENRO-DVS',
  `header_text` varchar(255) NOT NULL DEFAULT 'PENRO Disbursement Voucher System v1.0',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `system_name`, `page_title`, `company_name`, `browser_title`, `header_text`, `created_at`, `updated_at`) VALUES
(1, 'Voucher Tracking and Monitoring System', 'Voucher Tracking and Monitoring System', 'Provincial Environment and Natural Resources Office', 'VTraMS', 'VTraMS v1.0', '2026-01-27 00:02:28', '2026-05-05 23:00:49');

-- --------------------------------------------------------

--
-- Table structure for table `user_group`
--

CREATE TABLE `user_group` (
  `id` int(10) NOT NULL,
  `emp_id` varchar(255) NOT NULL,
  `emp_fn` varchar(50) NOT NULL,
  `emp_mi` varchar(50) NOT NULL,
  `emp_ln` varchar(50) NOT NULL,
  `office` varchar(255) NOT NULL,
  `section` varchar(255) NOT NULL,
  `division` varchar(255) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `udc` varchar(255) NOT NULL,
  `emp_tag` varchar(150) DEFAULT NULL,
  `access_level` int(10) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_group`
--

INSERT INTO `user_group` (`id`, `emp_id`, `emp_fn`, `emp_mi`, `emp_ln`, `office`, `section`, `division`, `designation`, `password`, `udc`, `emp_tag`, `access_level`, `created_at`) VALUES
(1, '0000', 'JULIUS RAYMUND', 'B.', 'TABINAS', 'DENR-PENRO EASTERN SAMAR', 'ICT', 'MSD', 'ICT Unit,System Admin', '$2y$12$dwCLiXaGN7WSokz/CsZwxO.LgCrYChkmUbVqc/yttMBhqE3jnS9fq', 'GhsCX', 'Other Professional Services', 999, '2024-06-09 08:01:24'),
(2, '0002', 'SUSAN CLAIRE', 'A.', 'SAMURAY', 'DENR-PENRO EASTERN SAMAR', 'RECORDS', 'MSD', 'Records Officer,Records Unit', '$2y$12$Z8vI1fbZIcPq7DW2U6SWGuGWpo07ZgFgR6B/smzhqBUyil3A7SA2W', 'L5Vbk', 'Other Professional Services', 5, '2024-06-09 08:53:24'),
(3, '0003', 'FERLYN', 'S.', 'CAHANAP', 'DENR-PENRO EASTERN SAMAR', 'OFFICE OF THE PENRO', 'OPENRO', 'Office of the PENRO', '$2y$12$7kIrc2slokQ/vtLwurqT7O3tnDZ6HpLWPg1NDN/BuHZ7mgY4Lp9Tu', 'e1fja', 'Other Professional Services', 3, '2024-06-09 15:05:50'),
(4, '0004', 'RAMEL', 'P.', 'JAYSON', 'DENR-PENRO EASTERN SAMAR', 'ADMIN AND FINANCE', 'MSD', 'Admin and Finance Section,Management Services Division', '$2y$12$/BL502wwOs3J8gxoCZSXA.xsaQItj8sx3PMrQQDelMY9D01snqVI6', 'jnpsn', 'Other Professional Services', 4, '2024-06-09 15:49:22'),
(5, '0005', 'LINDSEY', 'A.', 'BUNA', 'DENR-PENRO EASTERN SAMAR', 'ICT', 'MSD', '8888 Focal Person,GSIS AAO Focal Person,ICT Unit,Information Officer,System Admin', '$2y$12$dZmYfR1NugLIyP5mNCqzi.ae2Ux/gIOq1ZTu93P7.hxinuLCYNzH6', 'VzrKr', 'Other Professional Services', 8, '2024-06-09 18:03:56'),
(6, '0006', 'JENNY ROSE', 'T.', 'CORAL', 'DENR-PENRO EASTERN SAMAR', 'TSD', 'TSD', 'Technical Services Division Chief', '$2y$12$zLuquZHJWLrQJdKx0u8G3e9fUumOO4SGokzDy1Ev0.rvi/bcp025i', 'fTGII', 'Other Professional Services', 7, '2024-06-14 14:58:16'),
(7, '0007', 'AMOR', 'A.', 'ROBREDILLO', 'DENR-PENRO EASTERN SAMAR', 'MSD', 'MSD', 'Accounting Unit,Management Services Division Chief', '$2y$12$xl7daEIM55sWb5DZQKdwlOxc5hy1oqFhynlgIhrWdmfPgqAiT09Ga', '20r83', 'Other Professional Services', 7, '2024-06-21 09:00:39'),
(8, '0008', 'LEA', 'O.', 'TORRES', 'DENR-PENRO EASTERN SAMAR', 'OFFICE OF THE PENRO', 'OPENRO', 'PENR Officer', '$2y$12$hYP6YBaL5H2UvyEw1kB2C..AqlAK.3aRaRRcn7/nH17EDLjb22mZm', 'rMu7j', 'Other Professional Services', 7, '2024-06-21 09:01:57'),
(9, '0009', 'JAMAICA', 'L.', 'BASA', 'DENR-PENRO EASTERN SAMAR', 'RECORDS', 'MSD', 'Records Unit', '$2y$12$XG4fbBYaYZxdW3mAKCBIVulQh8Jiasspfkouk32kjfez8D8wg5KN2', 'fUpF9', 'Other Professional Services', 5, '2024-06-21 09:02:29'),
(10, '0010', 'BRENA', 'C.', 'TABINAS', 'DENR-PENRO EASTERN SAMAR', 'TSD', 'TSD', 'Technical Services Division', '$2y$12$mN5IpbR.0yonvRSAWu.kBOLQJqjBzat86ho2xHvBsFkxooBsp/CSy', 'UhI7N', 'Other Professional Services', 4, '2024-06-21 09:03:33'),
(11, '0011', 'FLORDELIZA', 'R.', 'LEGUARDA', 'DENR-PENRO EASTERN SAMAR', 'PLANNING', 'MSD', 'Citizens Charter Focal Person,CSS Focal Person,Planning Section,Planning Section Chief,SPICS Focal Person', '$2y$12$WJaTGQtIGf2oDpzm3RqhROatdiv/l4kp.y7phjhF251OHOHqMPq6S', 'phem1', 'Other Professional Services', 6, '2024-06-21 09:05:35'),
(12, '0012', 'MARGARETTE', 'A.', 'LUNA', 'DENR-PENRO EASTERN SAMAR', 'BUDGET', 'MSD', 'Budget Unit', '$2y$12$YZyXgA37HGw3QjOe0DHp4epBcywLdOPJawSzuumOWIdKK43oZoTpi', 'MsBfy', 'Other Professional Services', 4, '2024-06-21 09:19:39'),
(13, '0013', 'ARIEL', 'P.', 'BALANON', 'DENR-PENRO EASTERN SAMAR', 'BUDGET', 'MSD', 'Budget Unit', '$2y$12$lBxsggrawEOTCVJyof/.nejjtz6XhD6to/IqZZj8MrAPuggmZ9.KG', 'haV0Q', 'Other Professional Services', 4, '2024-06-21 09:20:24'),
(14, '0014', 'ERIC', 'P.', 'LAGUNZAD', 'DENR-PENRO EASTERN SAMAR', 'BUDGET', 'MSD', 'Budget Officer,Budget Unit,GAD Focal Person,Senior Citizen & PWD Focal Person', '$2y$12$K0FR3LwI6kg5eT1d2hpkneyVglCpAjGHEns7fDagTwCTck8.YWyFe', 'Jp4Nb', 'Other Professional Services', 6, '2024-06-21 09:21:19'),
(15, '0015', 'JUANNA ROSE', 'D.', 'DELMONTE', 'DENR-PENRO EASTERN SAMAR', 'ACCOUNTING', 'MSD', 'Accounting Unit', '$2y$12$0.1qhswWBI3Gt1CdCI/n5uVDDNG9mJclpc3YTk9kEBlJemIh4uDJC', 'vqliT', 'Other Professional Services', 4, '2024-06-21 09:24:33'),
(16, '0016', 'MARIFE', 'C.', 'BRITON', 'DENR-PENRO EASTERN SAMAR', 'ACCOUNTING', 'MSD', 'Accounting Unit,Processor', '$2y$12$zQZoSc4WTW7G2fLQlj/GDu3eOOa5R1U/ZUDJqHvWe6lQBFbXa8hRG', '4HyLy', 'Other Professional Services', 4, '2024-06-21 09:25:11'),
(17, '0017', 'GLENDA', 'O.', 'CUÑA', 'DENR-PENRO EASTERN SAMAR', 'ACCOUNTING', 'MSD', 'Accounting Unit', '$2y$12$rixgbwTgS.d4hF//AJxQ/uJdePxVFDoS4khsOBSP./Ys518RFOsVu', '7Ci0W', 'Other Professional Services', 3, '2024-06-21 09:26:10'),
(18, '0018', 'NORIEN', 'G.', 'TAVERA', 'DENR-PENRO EASTERN SAMAR', 'PERSONNEL &amp; GENERAL SERVICES', 'MSD', 'HDMF (Pagibig Fund) Focal Person,Personnel & General Services Unit', '$2y$12$CJ39p34diEsnGcNxyfiZyeq6L2ImizJb2bOif/SAbQgecj5AObOfe', 'AoKK3', 'Other Professional Services', 4, '2024-06-21 09:26:50'),
(19, '0019', 'JOCELYN', 'M.', 'OSTREA', 'DENR-PENRO EASTERN SAMAR', 'HR', 'MSD', 'HR', '$2y$12$xqcGpdBayqs6ccvxDdomqucRaKZkC4O5BPXqh05Ds/iin5zteVDMW', 'boURR', 'Other Professional Services', 3, '2024-06-21 09:27:35'),
(20, '0020', 'RONA', 'A.', 'BALDELOBAR', 'DENR-PENRO EASTERN SAMAR', 'CASHIER', 'MSD', 'Cashiers Unit', '$2y$12$RTGDH9rz5EF6QheD5aJzL..hbwcZzZx5iTHjwMs3VvIxKo3szSa8q', 'nQNYv', 'Other Professional Services', 4, '2024-06-21 09:32:22'),
(21, '0021', 'LENY', 'A.', 'ABUCAY', 'DENR-PENRO EASTERN SAMAR', 'CASHIER', 'MSD', 'Cashiers Unit', '$2y$12$ILlkMTnmb4pNPjVKDNKUhe3qmyH8UMywaj7LGIERle1sR56Xp6i1y', 'exh1u', 'Other Professional Services', 4, '2024-06-21 09:32:40'),
(22, '0022', 'ANTONIETTE', 'C.', 'DE LOS SANTOS', 'DENR-PENRO EASTERN SAMAR', 'CASHIER', 'MSD', 'Cashier,Cashiers Unit', '$2y$12$D5HZVzu6jcMH5UUhiCxoZ.bM2Sw/J8/Q4tc2fWLOZapdAupLduJWq', 'HQvui', 'Other Professional Services', 4, '2024-06-21 09:33:10'),
(23, '0023', 'MA. DULCE AMOR', 'S.', 'PILPA', 'DENR-PENRO EASTERN SAMAR', 'SUPPLY', 'MSD', 'Procurement & Supply Unit', '$2y$12$qDPF35jXOyrL4s9O.rk6wejkwK4IsyPxn/OHx.dyCmjtU4hZ1aNku', 'pywCX', 'Other Professional Services', 4, '2024-06-21 09:34:56'),
(24, '0024', 'GERVACIO', 'M.', 'AMBOY', 'DENR-PENRO EASTERN SAMAR', 'SUPPLY', 'MSD', 'Procurement & Supply Unit', '$2y$12$URvDxwdkOO4tGSGar2/OdO.GZriPBIRMooPkiJeqYvTT1LzN/7Aje', 'ARhbj', 'Other Professional Services', 3, '2024-06-21 09:35:43'),
(25, '0025', 'MELANIA', 'D.', 'CABER', 'DENR-PENRO EASTERN SAMAR', 'SUPPLY', 'MSD', 'ICU', '$2y$12$FcIBHkWVh0DyOSWrj56RReBR4lopQylfIAQw/qZmOwiKY2897v5M6', 'g1ii6', 'Other Professional Services', 3, '2024-06-21 09:36:05'),
(26, '0026', 'ROTCHELLE', 'L.', 'GAGAM', 'DENR-PENRO EASTERN SAMAR', 'SUPPLY', 'MSD', 'Procurement & Supply Unit,Supply Officer I', '$2y$12$Ab9QrrvfoDtu5tCWiJq2huKa9kMSNAXsW2aZESSa.naUMETDKWm22', 'DsSN2', 'Other Professional Services', 4, '2024-06-21 09:36:30'),
(27, '0027', 'RAUL', 'C.', 'CANTARA', 'DENR-PENRO EASTERN SAMAR', 'REGULATION &amp; PERMITTING', 'MSD', 'ADR Focal Person,Regulation & Permitting Section', '$2y$12$YhsYdrLwzElUnBnspN4xTeRnuI0tJhZLKtydWkydTEn5J8iZ19u.q', 'aI37k', 'Other Professional Services', 6, '2024-06-21 09:38:11'),
(28, '0028', 'JOSEPHINE', 'A.', 'AMLON', 'DENR-PENRO EASTERN SAMAR', 'REGULATION &amp; PERMITTING', 'TSD', 'Regulation & Permitting Section,Regulation & Permitting Section Chief', '$2y$12$PPD5oFUDuWJ8sGK8gq3e7ON5CVuswMpg3VlFmr.QIwS9CEwjkYCtC', 'gQtpt', 'Other Professional Services', 6, '2024-06-21 09:38:29'),
(29, '0029', 'THELMA', 'C.', 'ORTIGUESA', 'DENR-PENRO EASTERN SAMAR', 'CONSERVATION &amp; DEVELOPMENT', 'TSD', 'Conservation & Development Section,ENGP Focal Person', '$2y$12$EQ7enBCxGnYi1C1d5tlIUuiJkfs.AzBD6.jGd6HjP2LVmwJq9BL0S', 'w8lTV', 'Other Professional Services', 6, '2024-06-21 09:38:52'),
(30, '0030', 'LEANDRO', 'P.', 'OSTREA', 'DENR-PENRO EASTERN SAMAR', 'CONSERVATION &amp; DEVELOPMENT', 'TSD', 'Conservation & Development Section,Conservation & Development Section Chief', '$2y$12$.CU3iGA/heTUHZ9WBfAjz.782qAHClfObpEzdq8T2HO8iYl0Yvk8K', 'JjObD', 'Other Professional Services', 6, '2024-06-21 09:39:10'),
(31, '0031', 'EDGAR', 'C.', 'BORATA', 'DENR-PENRO EASTERN SAMAR', 'MONITORING &amp; ENFORCEMENT', 'TSD', 'Monitoring & Enforcement Section', '$2y$12$PQ2FNHRlKxmZU0Xid0/KkOuMrP/rmEhmoapoig1B1Ia16xOpRx4j.', '4TCk3', 'Other Professional Services', 4, '2024-06-21 09:39:23'),
(32, '0032', 'IRWIN ROBERTO', 'C.', 'VILLACARILLO', 'DENR-PENRO EASTERN SAMAR', 'MONITORING &amp; ENFORCEMENT', 'TSD', 'Monitoring & Enforcement Section,Monitoring & Enforcement Section Chief', '$2y$12$lqK5Uu6Zn2ZdA6YG6sQn1umgByy5nN4G60ala3EugNj4dplJ.KgdK', '6pOcf', 'Other Professional Services', 4, '2024-06-21 09:40:04'),
(33, '0033', 'MYRON', 'O.', 'GARCIA', 'DENR-PENRO EASTERN SAMAR', 'GMRPLS', 'TSD', 'PASu - GMRPLS', '$2y$12$kbHNiWqdYny2hrjXfsaZPOgb6ku/d/regDjgGVgHYLJ4VxocJLAAO', 'yDYxY', 'Other Professional Services', 4, '2024-06-21 09:40:17'),
(34, '0034', 'BABY BREND', 'B.', 'KISTNER', 'DENR-PENRO EASTERN SAMAR', 'PLANNING', 'MSD', 'Planning Section', '$2y$12$Yzcbuz03zbKW2kH/EJips.Uyoyeh5XTafaeP9EmmDAtP0fNaEEHcu', 'qvPnB', 'Other Professional Services', 4, '2024-07-11 21:56:39'),
(35, '0099', 'JANE', 'C.', 'BALEÑA', 'DENR-PENRO EASTERN SAMAR', 'Land Management Unit', 'OCENRO', 'Senior Citizen & PWD Focal Person', '$2y$12$ZTSVcYr62XvheUT0goT8Wuujh0xQwdca48ZoOTwXxycpvaFzanUOu', 'myrsb', 'Other Professional Services', 4, '2024-07-25 11:02:02'),
(36, '0035', 'GIANNA', 'C.', 'ALMAZAN', 'CENRO DOLORES', 'RECORDS', 'CENRO DOLORES', 'Records Officer,Records Unit', '$2y$12$JTsB5lUyRmzianBJiqdHHuFLBNLN5lRJGlYUu3glhYyTqUomqUtGG', '6V7ro', 'Other Professional Services', 5, '2024-08-12 12:26:51'),
(37, '0036', 'SALVACION', 'A.', 'FACTOR', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO Officer', '$2y$12$10Lgpu3QDQiQNzfExB0ht.sn3xkYpGq16WhBwdW/NDC98bmZh3x5a', 'AUzH7', 'Other Professional Services', 7, '2024-08-12 12:32:23'),
(38, '0037', 'MA. JAINA', 'C.', 'ARCA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Office of the CENRO', '$2y$12$i8gyf4Mw8EWcdHp1ydcE3OHGroIzH75E2JJ6u3iI28D/NBAZdi/KS', 'qBTBa', 'Other Professional Services', 3, '2024-08-12 12:36:06'),
(39, '0038', 'LELIT', 'L.', 'MAGDARAOG', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Admin Unit,CBFM Focal Person,Conservation & Development Section,Conservation & Development Section Chief', '$2y$12$wOjMXECMULN5s189pyqxheA7YB0gwjEHpNJ/DZ7COW4VsXcnJrYFe', 'MnUOc', 'Other Professional Services', 4, '2024-08-12 12:44:24'),
(40, '0039', 'ROGELIO', 'C.', 'CAFE', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Regulation & Permitting Section,Regulation & Permitting Section Chief', '$2y$12$.ChH9rhxExjWk3Q4X6f0X.7csumxT.M9IefJGpVvNVaJwV3gbvjpW', 'RXp4F', 'Other Professional Services', 4, '2024-08-12 12:45:03'),
(41, '0040', 'IAN', 'C.', 'ALMAZAN', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Monitoring & Enforcement Section,Monitoring & Enforcement Section Chief,Property Custodian', '$2y$12$C1yIktmkvINKmIl4./d8FeetMUW.MM/yatOoUUK.WvIfjGI7UCU2K', 'gnM6b', 'Other Professional Services', 4, '2024-08-12 12:45:50'),
(42, '0041', 'BERT', 'D.', 'EMPAS', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Planning Unit,Planning Unit Chief', '$2y$12$5dTgH0zqnyP5zwoFs12ehuKL3XWeUDyJQOW6BBZP3TFcdDaCuFnXy', 'WwDyZ', 'Other Professional Services', 4, '2024-08-12 14:23:19'),
(43, '0042', 'JAYSON', 'L.', 'ALFON', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'CBFM Unit,Information Officer,LAWIN,LAWIN Focal Person,SWIS Focal Person', '$2y$12$DRpbVrcDDIIOL/IJdKgxY.B14SQtfDaSGqibJrANL.0KIxE2njO5i', 'yIBVl', 'Other Professional Services', 4, '2024-08-13 10:44:43'),
(44, '0043', 'SHIELA MAE', 'M.', 'ORTIGUESA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Planning Unit', '$2y$12$5xYPuQUK1YkggRUjCu3mRutcdP6pSz/Sgn6fu9f9XiqnTRz8fVGR.', '20HBd', 'Other Professional Services', 4, '2024-08-13 11:30:44'),
(45, '0044', 'ZAIDA RONICA', 'M.', 'KHO', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Legal Researcher', '$2y$12$hFgvrwWkGDYe6gULKpqkOeIf2tiHWorwFOujvn36QJZevd8cbdMi2', 'iDP3J', 'Other Professional Services', 4, '2024-08-13 11:34:29'),
(46, '0045', 'IDA', 'O.', 'GODEN', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'EMB Focal Person,ENGP Focal Person,Reforestation / Afforestation Unit', '$2y$12$zeInVxpES5G2ll5rn6Xjrud5Zo0BHev5/cv64fcf3BYz/ZGRQoYbC', 'OFBzP', 'Other Professional Services', 4, '2024-08-13 11:35:09'),
(47, '0046', 'SMITH JIFF', 'G.', 'AGDA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'GAD Focal Person,Patents and Deeds,Senior Citizen & PWD Focal Person', '$2y$12$zxOF28qdyzyhDYpgCBWxyO0SZ4fCe9RBGULlRa9rLNq0dTFAM.BKu', 'lf6O7', 'Other Professional Services', 4, '2024-08-13 11:42:25'),
(48, '0047', 'MARY JOANNE', 'B.', 'RANAO', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'CSS Focal Person,Patents and Deeds', '$2y$12$Gsns3mpnvetSXpb88NBJoeKwwMExzHTyuDrfrEjgOaA/tugEq9P2i', 'Tsd8Q', 'Other Professional Services', 4, '2024-08-13 11:43:41'),
(49, '0048', 'FATIMA JOYCE', 'A.', 'ADAL', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Reforestation / Afforestation Unit', '$2y$12$i97UKUZXyoZBMK9txtlyAOpTZUr0rNGRY48wKaupTGI4RcglOfczm', 'Aii7Y', 'Other Professional Services', 4, '2024-08-13 11:44:42'),
(50, '0049', 'KIM', 'B.', 'POMAREJOS', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'CBFM Unit', '$2y$12$EDpDk92CxKZUp9cWkFPcHOT6zdbSV.Z2UxVg.xevtjn.RNkUhdYgq', 'EwVJy', 'Other Professional Services', 4, '2024-08-13 11:45:35'),
(51, '0050', 'ALFREDO', 'O.', 'OBINGAYAN', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'CBFM Unit', '$2y$12$Y2lpjAzjYEG5pgnoANZ6/eSbzD6UGw/EcxIFTuOdmq/TgWtScy5m6', 'ThBRz', 'Other Professional Services', 4, '2024-08-13 11:46:15'),
(52, '0051', 'MA. SOL', 'O.', 'OPENA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'CBFM Unit', '$2y$12$eSsH4fKocsZbsQHLhU5eHOY1QCrcpa.e8cRM1149KLBmaSQaGG3Fu', 'fNHDY', 'Other Professional Services', 4, '2024-08-13 11:46:41'),
(53, '0052', 'CONSTANCIO', 'P.', 'BAYABAY', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'CBFM Unit', '$2y$12$6.CEyQGO3UO5q8celGCan.21vtUAUcjJSHJXH0zc/eC7dTZwehiOa', 'OMWMb', 'Other Professional Services', 4, '2024-08-13 11:47:22'),
(54, '0053', 'MARIO ROY', 'B.', 'ABOBO', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Biodiversity Conservation Unit,Cave Focal Person,MGB Focal Person', '$2y$12$RgBuYsPnGmZM4POYdVRcG.eToNB1L7cemPvhIi.rZYAppJIm7d4Xu', 'H3E2K', 'Other Professional Services', 4, '2024-08-13 11:47:46'),
(55, '0054', 'ROSENDA', 'V.', 'BULA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Biodiversity Conservation Unit,Nursery Focal Person', '$2y$12$45882sHnxR5plGHNHV13iOWDKIXnGJ0dVrb2N3DHKfpiPUtCQ1jFC', 'aijjr', 'Other Professional Services', 4, '2024-08-13 11:48:34'),
(56, '0055', 'ELPIDIO', 'E.', 'DERILO', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'CBFM Unit', '$2y$12$nd4pkr3ijdwvKnVSU0E4seVgMzdaV1PB3iE5sYQoEeENhhmmId5gC', '4WAB7', 'Other Professional Services', 4, '2024-08-13 11:49:08'),
(57, '0056', 'RAYMOND', 'G.', 'AGDA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Forest Protection and Law Enforcement Unit', '$2y$12$yh/M5g9fawm4fdn5pJ9S6uqXzP6R6F3kJOZKl96cyOTAxByYTyNu.', 'mfEhG', 'Other Professional Services', 4, '2024-08-13 11:54:38'),
(58, '0057', 'JOSE ALLAN', 'P.', 'TURBANADA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Forest Protection and Law Enforcement Unit', '$2y$12$qyocPTk1ohfd8rypDVl4OOfjUH.0D0r8TdE6xjWURtLxJnQxdzYqO', 'MpAVl', 'Other Professional Services', 4, '2024-08-13 11:55:18'),
(59, '0058', 'NOEL', 'M.', 'SARONA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Forest Licensing / Utilization Unit,Forest Protection and Law Enforcement Unit', '$2y$12$z5DhIhsyRJUYEI/3HJquROAFGLs3rU/CoJSByc4NArdMj60yzv.jK', '4JF3R', 'Other Professional Services', 4, '2024-08-13 11:55:32'),
(60, '0059', 'JAMES MICHAEL', 'M.', 'LIAD', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Forest Protection and Law Enforcement Unit', '$2y$12$wrvJcVN7X99qQhf2u88iO..zgfr2TA1WUiVweIe2IEVEthIWcLwpG', 'QS6dy', 'Other Professional Services', 4, '2024-08-13 11:56:05'),
(61, '0060', 'WALLY', 'H.', 'BACHAO', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Forest Protection and Law Enforcement Unit', '$2y$12$HCBED.yJDdmPuAiyKst3ZOv6UdBFyRz.Vh6BOoGxuX2f/K2lIFkFu', 'hIpUt', 'Other Professional Services', 4, '2024-08-13 11:56:41'),
(62, '0061', 'JONATHAN', 'C.', 'CASIMERO', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Forest Protection and Law Enforcement Unit', '$2y$12$/quM43Zbzc/s4hmHTFdvnuXg1SKM9BRdPaVXGxsi0lJaC8Gt9NQxa', 'cnwIc', 'Other Professional Services', 4, '2024-08-13 11:57:15'),
(63, '0062', 'DINDO', 'M.', 'ORTIGUESA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Forest Protection and Law Enforcement Unit', '$2y$12$IgP38UTrgX5L8/qh6X8fouypLbLBOkZXcqwLrCrXYgSQDxmssKRum', 'OjVEg', 'Other Professional Services', 4, '2024-08-13 11:57:46'),
(64, '0063', 'LEOHEPOLITO', 'A.', 'BUNA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Forest Protection and Law Enforcement Unit', '$2y$12$eOvGlmgYIe0ABLEiZ6HBEOR8wM1fwqGh.QjM4/thnNgYHSKitE7Yy', 'HMZJP', 'Other Professional Services', 4, '2024-08-13 11:58:31'),
(65, '0064', 'BENJAMIN', 'M.', 'BALEÑA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'LAWIN', '$2y$12$ZtIPJZHQL8okUJUQB1zhuuGb1jq8fcBDi/7/f8ynwzS/I5UiWUugC', 'qCZts', 'Other Professional Services', 4, '2024-08-13 12:01:52'),
(66, '0065', 'RENE', 'C.', 'CAPOQUIAN', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'FLUP Focal Person,Forest Licensing / Utilization Unit,LAWIN', '$2y$12$ZCknZeReNHrRVPaT4hmVduGN7N7YaRI2WeK10bhg4VdVOM08IMFa6', 'Zm4fj', 'Other Professional Services', 4, '2024-08-13 12:02:36'),
(67, '0066', 'PAUL', 'L.', 'CUSTONA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Forest Licensing / Utilization Unit,LAWIN', '$2y$12$veJXbg6D/tDOdWkV20UC7uRSWf8h1T0YIDWHpbPvqs4cnxZu5Miw2', 'RRerh', 'Other Professional Services', 4, '2024-08-13 12:02:55'),
(68, '0067', 'JAY NORRIEL', 'B.', 'dela CRUZ', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Surveys and Mapping', '$2y$12$O63RVFy3q4OXhzWXkrA9dehh.1EIsmawFEuHNzq4U1w/nfhbQX0Pu', 'ZLJgs', 'Other Professional Services', 4, '2024-08-13 12:12:02'),
(69, '0068', 'AURELIO', 'M.', 'ARUTA', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Patents and Deeds,Surveys and Mapping', '$2y$12$3yGBfSq/GlZBLkAqhxeIh.UxeUi0Hh1vyioGHP5eSh5BAiXqxe2Se', 'Seuvp', 'Other Professional Services', 4, '2024-08-13 12:12:30'),
(70, '0069', 'DENNIS ADOLFO', 'S.', 'VELASCO', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Patents and Deeds', '$2y$12$1y2U1.Tv6iMIaQDfp1YLQe6lqsh1CcMsbehpSWKwE.5OpvJrtaHcK', 'miJlS', 'Other Professional Services', 4, '2024-08-13 12:13:26'),
(71, '0070', 'REYMUND', 'E.', 'MONTECAÑAS', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Patents and Deeds', '$2y$12$6za7Sx73a1PeamzCnjgQ8O8GGRL.homUUZI0pTx14aZhAu7Hb3Tvu', 'wh6XV', 'Other Professional Services', 4, '2024-08-13 12:14:25'),
(72, '0071', 'MARITES', 'L.', 'CARI', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Patents and Deeds', '$2y$12$LsttkuBClsBpUO2afPm8Vey7PLvuLIjnwmIdxolGIZHgZCVsX/KRO', '5C5So', 'Other Professional Services', 4, '2024-08-13 12:14:58'),
(73, '0072', 'CRISANDRO', 'T.', 'BROZAS', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Patents and Deeds', '$2y$12$WKSpFo01GbNdwDEz/V23iuUhcOf6Gtm1rrpK16cW2G1syGFgA9MOa', 'EWFEi', 'Other Professional Services', 4, '2024-08-13 12:15:29'),
(74, '0073', 'ARSENIO', 'C.', 'BORLAS', 'CENRO DOLORES', 'CENRO DOLORES', 'CENRO DOLORES', 'Patents and Deeds', '$2y$12$DOrQFMkp6J1Ss.euBU5Wy.PS7tSapSASV/IHkus9xYgUtHAX5.1A6', 'FxSOZ', 'Other Professional Services', 4, '2024-08-13 12:16:06'),
(75, '0074', 'GRACILE', 'B.', 'PALCE', 'DENR-PENRO EASTERN SAMAR', 'MSD', 'MSD', 'Processor', '$2y$12$J0gTPedF0LQFRlU9LjWJ3.YienYk1HCAcpk6YFZqUeDt2ma49yBDS', 's1JxV', 'Other Professional Services', 4, '2024-09-26 16:34:52'),
(76, '0075', 'CHRISTINE ANTOINETTE', 'A.', 'ROBREDILLO', 'DENR-PENRO EASTERN SAMAR', 'MSD', 'MSD', 'Processor', '$2y$12$I2DvzPvOyv/jVvV2p.oY8eFtmiLy9j8CemKIkYoSsaS5Dq4cA8bKa', 'LD6Eo', 'Other Professional Services', 4, '2024-09-26 16:35:47'),
(77, '0076', 'DIANA', 'E.', 'COSTUNA', 'DENR-PENRO EASTERN SAMAR', 'MSD', 'MSD', 'Processor', '$2y$12$/xtjXk9Pq.JycRaJ66pFh.LtCniB91J9FzanhQ2eM4xstOeHZ11Aq', 'YS9M3', 'Other Professional Services', 4, '2024-09-26 16:36:23'),
(78, '0077', 'BENJAMIN', 'O.', 'GONZALES', 'CENRO BORONGAN', 'Office of the CENRO', 'OCENRO', 'CENRO Officer', '$2y$12$6WjvItBvd6WljJ6mS5.N/.KU5vQfmPTdUr6d9y2shdted4mlNeqO2', 'vXcVj', 'Other Professional Services', 7, '2025-05-29 14:32:19'),
(79, '0078', 'LYKA', 'Q.', 'AFABLE', 'CENRO BORONGAN', 'Office of the CENRO', 'OCENRO', 'Office of the CENRO', '$2y$12$ZMLt/CrmRtZBUGlPftYsFu2jHLsKmx1SsFsM7FfcotOS6Qxl1/kIW', '9tqJw', 'Other Professional Services', 3, '2025-05-29 14:34:26'),
(80, '0079', 'IRWIN ROBERTO', 'C.', 'VILLACARILLO', 'CENRO BORONGAN', 'Office of the CENRO', 'OCENRO', 'DMO IV', '$2y$12$.Ovoylad0aiSIqF/oEBdNOLikjuWkfYI1mboT/HwpOy7vTPpAsruG', 'JhtgI', 'Other Professional Services', 5, '2025-05-29 14:49:51'),
(81, '0080', 'BLENDA', 'D.', 'ASERIOS', 'CENRO BORONGAN', 'Office of the CENRO', 'OCENRO', 'ARTA Focal Person,CSS Focal Person,Interim Admin', '$2y$12$vntXujmh61dbg0gpEq7UQeirk.L2REezmazR.3hksbLGyOWXOZLiO', '3JFRa', 'Other Professional Services', 5, '2025-05-29 14:51:15'),
(82, '0081', 'MARILOU', 'L.', 'BAQUILOD', 'CENRO BORONGAN', 'RECORDS', 'OCENRO', 'Records Officer,Records Unit', '$2y$12$zMITTQn.3NuNfGUbuOMkGeYKFBRC3dHloC/BS.FEzdIAavFf0Item', 'gHfmt', 'Other Professional Services', 5, '2025-05-29 14:55:40'),
(83, '0082', 'ALFREDO', 'C.', 'BULA', 'CENRO BORONGAN', 'Office of the CENRO', 'OCENRO', 'Supply Officer I', '$2y$12$FOeXuL5lMxw7PgEAHieNfek5QYGmOVGyOV0HaINPDEVyfaWyaM2Yy', 'nenmw', 'Other Professional Services', 4, '2025-05-29 14:57:38'),
(84, '0083', 'SAMANTHA ROSE', 'B.', 'DULFO', 'CENRO BORONGAN', 'Office of the CENRO', 'OCENRO', 'GAD Focal Person,Health and Wellness Focal Person,Information Officer,Interim Planning Officer,Liaison Officer', '$2y$12$hWXnmJ03MVprsWVBWczCk.Fiyjj26ACjTh/B7uUo96rLgy8JZ3VIe', 'pOyBY', 'Other Professional Services', 4, '2025-05-29 15:02:31'),
(85, '0084', 'ZOLITA', 'B.', 'DORADO', 'CENRO BORONGAN', 'Office of the CENRO', 'OCENRO', 'Receiving & Releasing Officer', '$2y$12$CAlD6S0la8wSabSjYtCz2uGfTA7V1xUl8ggIHD5n9Rlgd5QVK7DuK', 'ELZf7', 'Other Professional Services', 4, '2025-05-29 15:03:36'),
(86, '0085', 'VIVIAN', 'A.', 'CUADRA', 'CENRO BORONGAN', 'GMRPLS', 'OCENRO', 'Guiuan Marine Resource Protected Landscape & Seascape,Sr. EMS', '$2y$12$zTR.oq6Zc/cjerWrUd85W.fbTKYBBdSlxzJjMiCwinJ/g623LG3xG', 'Xa7q4', 'Other Professional Services', 4, '2025-05-29 15:09:19'),
(87, '0086', 'FELICITA NARCISA', 'B.', 'CERVANTES', 'CENRO BORONGAN', 'Conservation & Development Section', 'OCENRO', '8888 Focal Person,Conservation & Development Section,Conservation & Development Section Chief,EMS II', '$2y$12$U5jl1BE1bRyee85PoPXpbusyVN.cwBBkb7UDUUmTD2jiPxyynh6Ce', 'DeACB', 'Other Professional Services', 4, '2025-05-29 15:33:02'),
(88, '0087', 'ALEX', 'G.', 'ORIONDO', 'CENRO BORONGAN', 'Monitoring & Enforcement Section', 'OCENRO', 'Forester II,Monitoring & Enforcement Section,Monitoring & Enforcement Section Chief,OpCen Focal Person,Quick Response Team Focal Person', '$2y$12$iuv2cpnYaG/WhS1Fn17nVOiz28RzfV4JVDr.sfrnkGduLymMepiPS', 'GTpUT', 'Other Professional Services', 4, '2025-05-29 16:00:38'),
(89, '0088', 'VIRGIE', 'D.', 'TADLE', 'CENRO BORONGAN', 'Regulation & Permitting Section', 'OCENRO', 'LMO III,Regulation & Permitting Section,Regulation & Permitting Section Chief', '$2y$12$DmXjYIW2wXeRZA3X2fQaCu0.xdEvPa15OQUGsQtWHLU48zEy09Jny', 'iMi2u', 'Other Professional Services', 4, '2025-05-29 16:04:06'),
(90, '0089', 'PIO', 'O.', 'UDTUJAN', 'CENRO BORONGAN', 'Balangiga Sub-Station', 'OCENRO', 'Balangiga Sub-station,Forest Ranger', '$2y$12$Fx5vMllyxnLaKE6RUBwLYeLnrFcKHQRqvStmcGEiLA4bm.UcYztYi', 'XmVRC', 'Other Professional Services', 4, '2025-05-29 16:05:23'),
(91, '0090', 'CONRADO', 'O.', 'GODEN', 'CENRO BORONGAN', 'eNGP', 'OCENRO', 'Enhanced National Greening Program Unit,Forester I', '$2y$12$PTupuKEc4FcI/xN9GrOlCOkOIIUXh2wK.oF7tN7MQ9g.RYPBp5yMO', 'ryn2T', 'Other Professional Services', 4, '2025-05-29 16:06:12'),
(92, '0091', 'VICTOR', 'C.', 'NIBALVOS', 'CENRO BORONGAN', 'Community Based Forest Management Unit', 'OCENRO', 'CBFM Coordinator,CBFM Unit,Community Based Forest Management Unit,Forest Technician II', '$2y$12$uV9jZuwu/8OjHvGZC369XONwMb6KxujQICOh3yIE.60poALrMzCLe', 'rxNqS', 'Other Professional Services', 4, '2025-05-29 16:08:29'),
(93, '0092', 'MARK JOHN', 'A.', 'CODOY', 'CENRO BORONGAN', 'Inland Wetland & Wildlife Management Unit', 'OCENRO', 'Forester II,Inland Wetland and Wildlife Management Unit', '$2y$12$tLci1II5BHkhp9tfh.pRk.4l3E7h5E0pFxfv9Pxj3/4wFIKtVf/bi', 'e5wje', 'Other Professional Services', 4, '2025-05-29 16:09:53'),
(94, '0093', 'ROCEL', 'D.', 'PALADA', 'CENRO BORONGAN', 'Lawin Unit', 'OCENRO', 'Forest Technician I,LAWIN', '$2y$12$b1pIaPAWRSQhfDthpXSfjenEoRJfu2emUe4A3nENFNeaklaqN9Rza', 'mYQ0w', 'Other Professional Services', 4, '2025-05-29 16:10:44'),
(95, '0094', 'LEO', 'C.', 'AGARO', 'CENRO BORONGAN', 'Forest Protection & Law Enforcement Unit', 'OCENRO', 'Forest Protection and Law Enforcement Unit,Forester II', '$2y$12$OuXy6qrJIVxfnqPiP5sFZOOHa2/2e3G1vMrvZhqxORYw1Q73MFxaW', 'kiP9D', 'Other Professional Services', 4, '2025-05-29 16:11:54'),
(96, '0095', 'JAYSON', 'H.', 'GEROY', 'CENRO BORONGAN', 'Survey Unit', 'OCENRO', 'Engineer III,Survey Unit', '$2y$12$VUJHUMdzxYQoRV15QRFyMOXR3DOv46OrUyC2OV0bqCP5Sqv0bAzUS', 'xfuhe', 'Other Professional Services', 4, '2025-05-29 16:12:49'),
(97, '0096', 'JULIUS VIRGIL', 'M.', 'ABRAGAN', 'CENRO BORONGAN', 'Land Management Unit', 'OCENRO', '8888 Alternate Focal Person,Land Management Unit,LMO IV', '$2y$12$LRb7ej.hBv.G4HYH0er8/.QBgqLCzVAZ2Lq38e0UhIbiLiz0/d4K6', 'IzoJD', 'Other Professional Services', 4, '2025-05-29 16:13:41'),
(98, '0097', 'MARLO', 'B.', 'VALERA', 'CENRO BORONGAN', 'Forest Utilization & Permitting Unit', 'OCENRO', 'EMS I,Forest Utilization and Permitting Unit', '$2y$12$hkUdyS24GWNm/6pQreqiZ.d8RC//IkLMOjfwZSYyveZEZj9BrPocq', '4QjYe', 'Other Professional Services', 4, '2025-05-29 16:14:23'),
(99, '0098', 'EDLYN', 'P.', 'ALAMODIN', 'CENRO BORONGAN', 'Foreshore Area Management Unit', 'OCENRO', 'Foreshore Area Management Unit,PET-CBFM Focal Person', '$2y$12$aXc6QCnrt0PwisR0Z2nQFORzbFKViqFcIjnKLuLv1lFOHmaBqY45S', '0KxKi', 'Other Professional Services', 4, '2025-06-10 08:27:37'),
(100, '0099', 'JANE', 'C.', 'BALEÑA', 'CENRO BORONGAN', 'Land Management Unit', 'OCENRO', 'Senior Citizen & PWD Focal Person', '$2y$12$ZTSVcYr62XvheUT0goT8Wuujh0xQwdca48ZoOTwXxycpvaFzanUOu', 'Qrcd1', 'Other Professional Services', 4, '2025-06-10 08:29:08'),
(101, '0100', 'GLAIZA', 'E.', 'BALUNDO', 'CENRO BORONGAN', 'Forest Utilization & Permitting Unit', 'OCENRO', 'GIS & One Control Map Focal Person', '$2y$12$4MT2m0PFEtyNgpcrujorFOBn0wEuUXV2qAo/4j0EBPBOo/UZoodS2', 'xE4lw', 'Other Professional Services', 4, '2025-06-10 08:33:51'),
(102, '0101', 'RODEL', 'B.', 'BORDIOS', 'CENRO BORONGAN', 'Survey Unit', 'OCENRO', 'GIS & One Control Map Alternate Focal Person', '$2y$12$c2ZA3IjI0G4hbsCqtIh91eno12ZkYmkISQLUur.027hXz822HqeeC', 'fgpJd', 'Other Professional Services', 4, '2025-06-10 08:34:45'),
(103, '0102', 'RONNIE', 'L.', 'BUNA', 'CENRO BORONGAN', 'Land Management Unit', 'OCENRO', 'ADR Focal Person', '$2y$12$JIXZJrOx8NltZ.VcKXxv7OK.42ey9YrRnoQ9vy59LNTBPGWT1w89.', 'YNCXO', 'Other Professional Services', 4, '2025-06-10 16:31:23'),
(104, '0103', 'JAMES ARMAN', 'A.', 'LAPISBORO', 'CENRO BORONGAN', 'EMB', 'OCENRO', 'EMB Personnel', '$2y$12$H5Eh10cOiib9ohzGKr3hrujFauxQ5UElikoICCgZTsLptHZvsibJe', 'iRtEl', 'Other Professional Services', 4, '2025-07-10 13:21:00'),
(105, '0104', 'DIVINA', 'L.', 'SABUSAB', 'CENRO BORONGAN', 'Office of the CENRO', 'OCENRO', 'Credit Officer', '$2y$12$ugUfUrLCZv4omut/5po84.sbWPqaHxKeT.70OVtIzRmnR81v3n8y6', 'IzRFV', 'Other Professional Services', 4, '2025-07-29 11:11:20'),
(106, '0105', 'AMADO', 'D.', 'ODICTA', 'CENRO BORONGAN', 'ICT Unit', 'OCENRO', 'IT Focal Person', '$2y$12$6c/1Qs4CCPgh.yoiLb9CaO7Zs03Xr7zBbftjbLyKR1IVuIjLVsxb6', 'cGwtk', 'Other Professional Services', 4, '2025-10-15 16:42:21'),
(107, '0106', 'NATHALLIE', 'D.', 'BALEÑA', 'DENR-PENRO EASTERN SAMAR', 'Accounting', 'MSD', 'Accountant III', '$2y$12$m8HCazXy3isLOD9z/6j5iudBVqs0k8/7JhRXbjjjjEgeUyb1KOqxK', 'qs7lT', 'Other Professional Services', 7, '2025-11-21 08:50:27'),
(108, '0107', 'JOHN PAUL SIMEON', 'P.', 'SAWAL', 'DENR-PENRO EASTERN SAMAR', 'TSD', 'TSD', 'System Admin', '$2y$12$uwq87SlblgIhrJIw88XLxePFugcmZEegSL6eLXEiCouzT4MMt2H4a', 'WPeLS', 'Other Professional Services', 4, '2026-01-22 09:47:24');

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(10) NOT NULL,
  `processing_no` varchar(255) NOT NULL,
  `dv_no` varchar(255) NOT NULL,
  `ada_check_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `payee` varchar(50) NOT NULL,
  `tin_employee_no` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `amount` float NOT NULL,
  `voucher_type` varchar(255) NOT NULL,
  `voucher_date` varchar(255) NOT NULL,
  `particulars` varchar(2000) NOT NULL,
  `datetime_encoded` varchar(50) DEFAULT 'N/A',
  `encoded_from` varchar(50) NOT NULL,
  `encoded_by` varchar(50) NOT NULL,
  `coa_options` text DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
  `coa_category` varchar(255) DEFAULT NULL COMMENT 'Selected COA category',
  `coa_subsection` varchar(255) DEFAULT NULL COMMENT 'Selected COA subsection',
  `return_remarks` text DEFAULT NULL COMMENT 'Remarks entered when voucher was returned from Incoming',
  `process_history` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_action_logs`
--

CREATE TABLE `voucher_action_logs` (
  `id` bigint(20) NOT NULL,
  `processing_no` varchar(255) NOT NULL,
  `ors_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `ada_check_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `dv_no` varchar(255) NOT NULL,
  `payee` varchar(255) NOT NULL,
  `address` varchar(1000) NOT NULL,
  `tin_employee_no` varchar(255) NOT NULL,
  `particulars` varchar(1000) NOT NULL,
  `amount` int(10) NOT NULL,
  `voucher_type` varchar(255) NOT NULL,
  `voucher_date` varchar(255) NOT NULL,
  `remarks` varchar(2000) NOT NULL,
  `process_history` text DEFAULT NULL,
  `encoded_by` varchar(500) NOT NULL,
  `action` varchar(255) NOT NULL,
  `action_by` varchar(500) NOT NULL,
  `action_from` varchar(250) NOT NULL,
  `datetime_action` varchar(255) NOT NULL,
  `office_from` varchar(500) NOT NULL,
  `office_to` varchar(500) NOT NULL,
  `coa_options` text DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
  `coa_category` varchar(255) DEFAULT NULL COMMENT 'Selected COA category',
  `coa_subsection` varchar(255) DEFAULT NULL COMMENT 'Selected COA subsection'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_archives`
--

CREATE TABLE `voucher_archives` (
  `id` bigint(20) NOT NULL,
  `processing_no` varchar(255) NOT NULL,
  `ors_no` varchar(255) DEFAULT NULL,
  `ada_check_no` varchar(255) DEFAULT NULL,
  `dv_no` varchar(255) NOT NULL,
  `payee` varchar(255) NOT NULL,
  `address` varchar(1000) NOT NULL,
  `tin_employee_no` varchar(255) NOT NULL,
  `particulars` varchar(1000) NOT NULL,
  `amount` int(10) NOT NULL,
  `charged_amount` int(10) DEFAULT NULL,
  `voucher_type` varchar(255) NOT NULL,
  `certified_correct` varchar(500) DEFAULT NULL,
  `approved_by` varchar(500) DEFAULT NULL,
  `agency_authorized_signatory` varchar(500) DEFAULT NULL,
  `voucher_date` varchar(255) NOT NULL,
  `ada_check_date` varchar(255) NOT NULL,
  `encoded_by` varchar(500) NOT NULL,
  `datetime_encoded` varchar(500) NOT NULL,
  `action` varchar(255) NOT NULL,
  `action_by` varchar(500) NOT NULL,
  `datetime_action` varchar(255) NOT NULL,
  `office_from` varchar(500) NOT NULL,
  `office_to` varchar(500) NOT NULL,
  `remarks` varchar(2000) NOT NULL,
  `process_history` text DEFAULT NULL,
  `supporting_documents` varchar(2000) DEFAULT NULL,
  `coa_options` text DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
  `coa_category` varchar(255) DEFAULT NULL COMMENT 'Selected COA category',
  `coa_subsection` varchar(255) DEFAULT NULL COMMENT 'Selected COA subsection'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_incoming`
--

CREATE TABLE `voucher_incoming` (
  `id` bigint(20) NOT NULL,
  `processing_no` varchar(255) NOT NULL,
  `ors_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `ada_check_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `dv_no` varchar(255) NOT NULL,
  `payee` varchar(500) NOT NULL,
  `address` varchar(1000) NOT NULL DEFAULT 'N/A',
  `tin_employee_no` varchar(255) NOT NULL,
  `particulars` varchar(2000) NOT NULL,
  `amount` int(10) NOT NULL,
  `charged_amount` int(10) DEFAULT NULL,
  `voucher_type` varchar(255) NOT NULL,
  `voucher_date` varchar(255) NOT NULL,
  `datetime_encoded` varchar(500) NOT NULL,
  `datetime_forwarded` varchar(255) NOT NULL,
  `sender_udc` varchar(255) NOT NULL,
  `receiver_udc` varchar(255) NOT NULL,
  `office_from` varchar(500) NOT NULL,
  `office_to` varchar(500) NOT NULL,
  `encoded_by` varchar(500) NOT NULL,
  `encoded_from` varchar(500) NOT NULL,
  `forwarded_by` varchar(500) NOT NULL,
  `process_status` varchar(255) NOT NULL,
  `remarks` varchar(2000) NOT NULL,
  `sender_remarks` varchar(2000) NOT NULL,
  `process_history` text DEFAULT NULL,
  `supporting_documents` varchar(2000) DEFAULT NULL,
  `coa_options` text DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
  `coa_category` varchar(255) DEFAULT NULL COMMENT 'Selected COA category',
  `coa_subsection` varchar(255) DEFAULT NULL COMMENT 'Selected COA subsection'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_receiving`
--

CREATE TABLE `voucher_receiving` (
  `id` bigint(20) NOT NULL,
  `processing_no` varchar(255) NOT NULL,
  `ors_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `ada_check_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `dv_no` varchar(255) NOT NULL,
  `payee` varchar(500) NOT NULL,
  `address` varchar(1000) NOT NULL DEFAULT 'N/A',
  `tin_employee_no` varchar(255) NOT NULL,
  `particulars` varchar(2000) NOT NULL,
  `amount` int(10) NOT NULL,
  `charged_amount` int(10) DEFAULT NULL,
  `voucher_type` varchar(255) NOT NULL,
  `voucher_date` varchar(255) NOT NULL,
  `datetime_forwarded` varchar(255) NOT NULL,
  `sender_udc` varchar(255) NOT NULL,
  `receiver_udc` varchar(255) NOT NULL,
  `office_from` varchar(500) NOT NULL,
  `office_to` varchar(500) NOT NULL,
  `encoded_by` varchar(500) NOT NULL,
  `encoded_from` varchar(500) NOT NULL,
  `datetime_encoded` varchar(500) NOT NULL,
  `forwarded_by` varchar(500) NOT NULL,
  `transmit` varchar(255) NOT NULL DEFAULT 'No',
  `process_status` varchar(250) NOT NULL,
  `remarks` varchar(2000) NOT NULL,
  `sender_remarks` varchar(2000) NOT NULL,
  `process_history` text DEFAULT NULL,
  `supporting_documents` varchar(2000) DEFAULT NULL,
  `coa_options` text DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
  `coa_category` varchar(255) DEFAULT NULL COMMENT 'Selected COA category',
  `coa_subsection` varchar(255) DEFAULT NULL COMMENT 'Selected COA subsection'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_sent`
--

CREATE TABLE `voucher_sent` (
  `id` bigint(20) NOT NULL,
  `processing_no` varchar(255) NOT NULL,
  `ors_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `ada_check_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `dv_no` varchar(255) NOT NULL,
  `payee` varchar(500) NOT NULL,
  `address` varchar(1000) NOT NULL DEFAULT 'N/A',
  `tin_employee_no` varchar(255) NOT NULL,
  `particulars` varchar(2000) NOT NULL,
  `amount` int(10) NOT NULL,
  `charged_amount` int(10) DEFAULT NULL,
  `voucher_type` varchar(255) NOT NULL,
  `voucher_date` varchar(255) NOT NULL,
  `datetime_encoded` varchar(500) NOT NULL,
  `datetime_forwarded` varchar(255) NOT NULL,
  `sender_udc` varchar(255) NOT NULL,
  `receiver_udc` varchar(255) NOT NULL,
  `office_from` varchar(500) NOT NULL,
  `office_to` varchar(500) NOT NULL,
  `encoded_by` varchar(500) NOT NULL,
  `encoded_from` varchar(500) NOT NULL,
  `forwarded_by` varchar(500) NOT NULL,
  `process_status` varchar(255) NOT NULL,
  `remarks` varchar(2000) NOT NULL,
  `sender_remarks` varchar(2000) NOT NULL,
  `process_history` text DEFAULT NULL,
  `supporting_documents` varchar(2000) DEFAULT NULL,
  `coa_options` text DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
  `coa_category` varchar(255) DEFAULT NULL COMMENT 'Selected COA category',
  `coa_subsection` varchar(255) DEFAULT NULL COMMENT 'Selected COA subsection'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_signatories`
--

CREATE TABLE `voucher_signatories` (
  `id` int(11) NOT NULL,
  `signatory_key` varchar(64) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `position_line1` varchar(255) NOT NULL DEFAULT '',
  `position_line2` varchar(255) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voucher_signatories`
--

INSERT INTO `voucher_signatories` (`id`, `signatory_key`, `display_name`, `position_line1`, `position_line2`, `is_active`, `updated_at`) VALUES
(1, 'dv_accounting_certified', 'NATHALLIE D. BALEÑA', 'Accountant III', 'Head Accounting Unit/Authorized Representative', 1, NULL),
(3, 'dv_approved_for_payment', 'Forester Lea O. Torres', 'PENR Officer', 'Agency Head/Authorized Representative', 1, NULL),
(4, 'dv_certified_msd', 'AMOR A. ROBREDILLO', 'Chief, Management Services Division', 'Printed Name, Designation and Signature of Supervisor', 1, NULL),
(5, 'dv_certified_tsd', 'JENNY ROSE T. CORAL', 'Chief, Technical Services Division', 'Printed Name, Designation and Signature of Supervisor', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `voucher_temp`
--

CREATE TABLE `voucher_temp` (
  `id` bigint(20) NOT NULL,
  `processing_no` varchar(255) NOT NULL,
  `ors_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `ada_check_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `dv_no` varchar(255) NOT NULL,
  `payee` varchar(255) NOT NULL,
  `address` varchar(1000) NOT NULL,
  `tin_employee_no` varchar(255) NOT NULL,
  `particulars` varchar(1000) NOT NULL,
  `amount` int(10) NOT NULL,
  `charged_amount` int(10) DEFAULT NULL,
  `voucher_type` varchar(255) NOT NULL,
  `voucher_date` varchar(255) NOT NULL,
  `remarks` varchar(5000) NOT NULL,
  `process_history` text DEFAULT NULL,
  `supporting_documents` varchar(2000) DEFAULT NULL,
  `encoded_by` varchar(500) NOT NULL,
  `encoded_from` varchar(500) NOT NULL,
  `datetime_encoded` varchar(500) NOT NULL,
  `receiver_udc` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `action_by` varchar(500) NOT NULL,
  `datetime_action` varchar(255) NOT NULL,
  `office_from` varchar(500) NOT NULL,
  `office_to` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_tracking`
--

CREATE TABLE `voucher_tracking` (
  `id` bigint(20) NOT NULL,
  `processing_no` varchar(255) NOT NULL,
  `ors_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `ada_check_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `ada_check_date` varchar(255) NOT NULL DEFAULT 'TBD',
  `dv_no` varchar(255) NOT NULL DEFAULT 'TBD',
  `payee` varchar(500) NOT NULL,
  `address` varchar(1000) NOT NULL DEFAULT 'N/A',
  `particulars` varchar(2000) NOT NULL,
  `amount` int(10) NOT NULL,
  `charged_amount` int(10) DEFAULT NULL,
  `voucher_type` varchar(255) NOT NULL,
  `voucher_date` varchar(255) NOT NULL,
  `datetime_encoded` varchar(255) NOT NULL,
  `voucher_status` varchar(255) NOT NULL,
  `status` varchar(1000) NOT NULL DEFAULT 'TBD',
  `datetime_status` varchar(255) NOT NULL,
  `remarks` varchar(2000) NOT NULL,
  `process_history` text DEFAULT NULL,
  `supporting_documents` varchar(2000) DEFAULT NULL,
  `total_processing_time` varchar(500) NOT NULL DEFAULT 'TBD',
  `encoded_by` varchar(500) NOT NULL,
  `office_to` varchar(500) NOT NULL,
  `office_from` varchar(500) NOT NULL,
  `coa_options` text DEFAULT NULL COMMENT 'JSON string of selected COA requirements',
  `coa_category` varchar(255) DEFAULT NULL COMMENT 'Selected COA category',
  `coa_subsection` varchar(255) DEFAULT NULL COMMENT 'Selected COA subsection'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `action_logs`
--
ALTER TABLE `action_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ada_signatory_options`
--
ALTER TABLE `ada_signatory_options`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_type_value` (`option_type`,`option_value`),
  ADD KEY `idx_type_active_sort` (`option_type`,`is_active`,`sort_order`,`option_value`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ip_address` (`ip_address`);

--
-- Indexes for table `designation_limit`
--
ALTER TABLE `designation_limit`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dv_entries`
--
ALTER TABLE `dv_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dv_entries_processing_no` (`processing_no`);

--
-- Indexes for table `encoded_voucher_no`
--
ALTER TABLE `encoded_voucher_no`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `routing`
--
ALTER TABLE `routing`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_group`
--
ALTER TABLE `user_group`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `voucher_action_logs`
--
ALTER TABLE `voucher_action_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `voucher_archives`
--
ALTER TABLE `voucher_archives`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `voucher_incoming`
--
ALTER TABLE `voucher_incoming`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `voucher_receiving`
--
ALTER TABLE `voucher_receiving`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `voucher_sent`
--
ALTER TABLE `voucher_sent`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `voucher_signatories`
--
ALTER TABLE `voucher_signatories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_key` (`signatory_key`);

--
-- Indexes for table `voucher_temp`
--
ALTER TABLE `voucher_temp`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `voucher_tracking`
--
ALTER TABLE `voucher_tracking`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `action_logs`
--
ALTER TABLE `action_logs`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ada_signatory_options`
--
ALTER TABLE `ada_signatory_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `designation_limit`
--
ALTER TABLE `designation_limit`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `dv_entries`
--
ALTER TABLE `dv_entries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encoded_voucher_no`
--
ALTER TABLE `encoded_voucher_no`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `routing`
--
ALTER TABLE `routing`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_group`
--
ALTER TABLE `user_group`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `voucher_action_logs`
--
ALTER TABLE `voucher_action_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `voucher_archives`
--
ALTER TABLE `voucher_archives`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `voucher_incoming`
--
ALTER TABLE `voucher_incoming`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `voucher_receiving`
--
ALTER TABLE `voucher_receiving`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `voucher_sent`
--
ALTER TABLE `voucher_sent`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `voucher_signatories`
--
ALTER TABLE `voucher_signatories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `voucher_temp`
--
ALTER TABLE `voucher_temp`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `voucher_tracking`
--
ALTER TABLE `voucher_tracking`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
