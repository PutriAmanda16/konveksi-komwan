-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2026 at 07:15 AM
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
-- Database: `konveksi2`
--

-- --------------------------------------------------------

--
-- Table structure for table `aset`
--

CREATE TABLE `aset` (
  `ID_ASET` varchar(10) NOT NULL,
  `ID_OWNER` varchar(10) DEFAULT NULL,
  `NAMA_ASET` varchar(50) NOT NULL,
  `JENIS_ASET` varchar(50) NOT NULL,
  `NILAI_ASET` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aset`
--

INSERT INTO `aset` (`ID_ASET`, `ID_OWNER`, `NAMA_ASET`, `JENIS_ASET`, `NILAI_ASET`) VALUES
('AST01', 'OWN1', 'Mesin Jahit Juki', 'Produksi', 5500000),
('AST02', 'OWN1', 'Mesin Obras', 'Produksi', 4500000),
('AST03', 'OWN1', 'Mesin Potong Kain', 'Produksi', 3000000),
('AST04', 'OWN1', 'Meja Jahit', 'Peralatan', 1500000),
('AST05', 'OWN1', 'Setrika Uap', 'Peralatan', 2000000),
('AST06', 'OWN1', 'Rak Penyimpanan', 'Inventaris', 1200000),
('AST07', 'OWN1', 'Komputer Admin', 'Elektronik', 6500000),
('AST08', 'OWN1', 'Printer', 'Elektronik', 2500000),
('AST09', 'OWN1', 'Etalase Produk', 'Inventaris', 1800000),
('AST10', 'OWN1', 'Mesin Bordir', 'Produksi', 7500000);

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id_log` int(11) NOT NULL,
  `nama_tabel` varchar(50) DEFAULT NULL,
  `aksi` enum('INSERT','UPDATE','DELETE') DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `waktu` datetime DEFAULT NULL,
  `user_pelaksana` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id_log`, `nama_tabel`, `aksi`, `keterangan`, `waktu`, `user_pelaksana`) VALUES
(1, 'pelanggan', 'INSERT', 'Menambahkan pelanggan baru: SDN 1 DKP', '2025-12-30 22:35:35', 'root@localhost'),
(2, 'pelanggan', 'INSERT', 'Menambahkan pelanggan baru: SDN 2 DKP', '2025-12-30 22:42:27', 'root@localhost'),
(3, 'pelanggan', 'UPDATE', 'Mengubah data pelanggan ID: PLG09', '2025-12-30 22:43:18', 'root@localhost'),
(4, 'pelanggan', 'UPDATE', 'Mengubah data pelanggan ID: PLG09', '2025-12-30 22:43:28', 'root@localhost'),
(5, 'pelanggan', 'DELETE', 'Menghapus pelanggan: SDN 2 DKP', '2025-12-30 22:43:41', 'root@localhost');

-- --------------------------------------------------------

--
-- Table structure for table `bahan_baku`
--

CREATE TABLE `bahan_baku` (
  `ID_BAHAN` varchar(10) NOT NULL,
  `NAMA_BAHAN` varchar(50) NOT NULL,
  `JUMLAH_STOK` int(11) NOT NULL,
  `HARGA_SATUAN` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bahan_baku`
--

INSERT INTO `bahan_baku` (`ID_BAHAN`, `NAMA_BAHAN`, `JUMLAH_STOK`, `HARGA_SATUAN`) VALUES
('BHN01', 'Kain Katun', 10, 25000),
('BHN02', 'Kain Drill', 50, 40000),
('BHN03', 'Benang Jahit', 200, 5000),
('BHN04', 'Kancing Plastik', 500, 200),
('BHN05', 'GOLDEN MELA', 170, 110000),
('BHN09', 'Resleting', 100, 2500),
('BHN10', 'famatex', 23, 20000);

-- --------------------------------------------------------

--
-- Table structure for table `detail_pembelian`
--

CREATE TABLE `detail_pembelian` (
  `ID_DETAIL_BELI` varchar(10) NOT NULL,
  `ID_BAHAN` varchar(10) DEFAULT NULL,
  `ID_PEMBELIAN` varchar(10) DEFAULT NULL,
  `JUMLAH_BELI` int(11) NOT NULL,
  `SUBTOTAL_BELI` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_pembelian`
--

INSERT INTO `detail_pembelian` (`ID_DETAIL_BELI`, `ID_BAHAN`, `ID_PEMBELIAN`, `JUMLAH_BELI`, `SUBTOTAL_BELI`) VALUES
('DB01', 'BHN01', 'PB01', 50, 1250000),
('DB02', 'BHN02', 'PB01', 30, 1200000),
('DB03', 'BHN03', 'PB02', 100, 500000),
('DB04', 'BHN04', 'PB03', 200, 40000),
('DB05', 'BHN05', 'PB04', 20, 2200000);

-- --------------------------------------------------------

--
-- Table structure for table `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `ID_DETAIL` int(11) NOT NULL,
  `ID_PESANAN` varchar(10) DEFAULT NULL,
  `ID_PRODUK` varchar(10) DEFAULT NULL,
  `JUMLAH` int(11) NOT NULL,
  `UKURAN` varchar(10) DEFAULT NULL,
  `SUBTOTAL` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_pesanan`
--

INSERT INTO `detail_pesanan` (`ID_DETAIL`, `ID_PESANAN`, `ID_PRODUK`, `JUMLAH`, `UKURAN`, `SUBTOTAL`) VALUES
(3, 'PSN02', 'PRD02', 20, 'L', 1200000),
(4, 'PSN04', 'PRD03', 8, 'M', 560000),
(5, 'PSN06', 'PRD04', 20, 'L', 2800000),
(12, 'PSN03', 'PRD08', 8, 'ALL SIZE', 240000),
(13, 'PSN05', 'PRD01', 20, 'M', 2860000),
(26, 'PSN07', 'PRD04', 20, NULL, 2800000),
(27, 'PSN08', 'PRD08', 10, 'ALL SIZE', 300000),
(29, 'PSN01', 'PRD01', 15, 'XL', 2175000),
(30, 'PSN09', 'PRD04', 20, 'L', 2800000);

-- --------------------------------------------------------

--
-- Table structure for table `log_recovery_system`
--

CREATE TABLE `log_recovery_system` (
  `id_recovery` int(11) NOT NULL,
  `nama_petugas` varchar(50) DEFAULT NULL,
  `aktivitas_pemulihan` varchar(255) DEFAULT NULL,
  `status_pemulihan` enum('Berhasil','Gagal') DEFAULT NULL,
  `waktu_eksekusi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `log_recovery_system`
--

INSERT INTO `log_recovery_system` (`id_recovery`, `nama_petugas`, `aktivitas_pemulihan`, `status_pemulihan`, `waktu_eksekusi`) VALUES
(1, 'Nadia_Admin', 'Restore database konveksi2 dari file cadangan_tahunan.sql', 'Berhasil', '2025-12-30 12:05:58');

-- --------------------------------------------------------

--
-- Table structure for table `owner`
--

CREATE TABLE `owner` (
  `ID_OWNER` varchar(10) NOT NULL,
  `NAMA_OWNER` varchar(50) NOT NULL,
  `NO_WA` int(11) NOT NULL,
  `ALAMAT_OWNER` varchar(100) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `owner`
--

INSERT INTO `owner` (`ID_OWNER`, `NAMA_OWNER`, `NO_WA`, `ALAMAT_OWNER`, `username`, `password`) VALUES
('OWN1', 'RAISSA', 0, 'SURABAYA', 'OWNER', 'ownerkonveksi');

-- --------------------------------------------------------

--
-- Table structure for table `pelanggan`
--

CREATE TABLE `pelanggan` (
  `ID_PELANGGAN` varchar(10) NOT NULL,
  `NAMA_PELANGGAN` varchar(25) NOT NULL,
  `ALAMAT_PELANGGAN` varchar(100) NOT NULL,
  `NO_HP` bigint(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pelanggan`
--

INSERT INTO `pelanggan` (`ID_PELANGGAN`, `NAMA_PELANGGAN`, `ALAMAT_PELANGGAN`, `NO_HP`, `username`, `password`) VALUES
('PLG01', 'TK Kusuma Bangsa', 'Jl. Dukuh Menanggal I No.16', 62318534248, 'pelanggan', '123'),
('PLG02', 'SD Harapan', 'Malang', 812345679, 'plg2', '123'),
('PLG03', 'SMP Cendekia', 'Sidoarjo', 812345680, 'plg3', '123'),
('PLG04', 'SMA Pelita', 'Gresik', 812345681, 'plg4', '123'),
('PLG05', 'TK Melati', 'Lamongan', 812345682, 'plg5', '123'),
('PLG06', 'TK cantikan', 'jombang', 6285230291923, 'tk_cantikan', '123'),
('PLG09', 'SDN 1 DKP', 'CIREBON', 88223509660, 'CRB1', '123456');

--
-- Triggers `pelanggan`
--
DELIMITER $$
CREATE TRIGGER `log_delete_pelanggan` AFTER DELETE ON `pelanggan` FOR EACH ROW BEGIN
    INSERT INTO audit_log (nama_tabel, aksi, keterangan, waktu, user_pelaksana)
    VALUES ('pelanggan', 'DELETE', CONCAT('Menghapus pelanggan: ', OLD.NAMA_PELANGGAN), NOW(), USER());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_insert_pelanggan` AFTER INSERT ON `pelanggan` FOR EACH ROW BEGIN
    INSERT INTO audit_log (nama_tabel, aksi, keterangan, waktu, user_pelaksana)
    VALUES ('pelanggan', 'INSERT', CONCAT('Menambahkan pelanggan baru: ', NEW.NAMA_PELANGGAN), NOW(), USER());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_update_pelanggan` AFTER UPDATE ON `pelanggan` FOR EACH ROW BEGIN
    INSERT INTO audit_log (nama_tabel, aksi, keterangan, waktu, user_pelaksana)
    VALUES ('pelanggan', 'UPDATE', CONCAT('Mengubah data pelanggan ID: ', OLD.ID_PELANGGAN), NOW(), USER());
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `pemasukan`
--

CREATE TABLE `pemasukan` (
  `ID_PEMASUKAN` varchar(10) NOT NULL,
  `ID_OWNER` varchar(10) DEFAULT NULL,
  `TANGGAL_PEMASUKAN` date NOT NULL,
  `JUMLAH_PEMASUKAN` int(11) NOT NULL,
  `SUMBER` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pemasukan`
--

INSERT INTO `pemasukan` (`ID_PEMASUKAN`, `ID_OWNER`, `TANGGAL_PEMASUKAN`, `JUMLAH_PEMASUKAN`, `SUMBER`) VALUES
('PEM006', 'OWN1', '2025-12-27', 1400000, 'Pembayaran PSN06'),
('PM01', 'OWN01', '2025-02-10', 5000000, 'Pesanan'),
('PM02', 'OWN01', '2025-02-11', 3000000, 'Pesanan'),
('PM03', 'OWN01', '2025-02-12', 2500000, 'Pesanan'),
('PM04', 'OWN01', '2025-02-13', 4500000, 'Pesanan'),
('PM05', 'OWN01', '2025-02-14', 3500000, 'Pesanan');

-- --------------------------------------------------------

--
-- Table structure for table `pembelian_bahan`
--

CREATE TABLE `pembelian_bahan` (
  `ID_PEMBELIAN` varchar(10) NOT NULL,
  `ID_SUPPLIER` varchar(10) DEFAULT NULL,
  `ID_OWNER` varchar(10) DEFAULT NULL,
  `TANGGAL_BELI` date NOT NULL,
  `TOTAL_BIAYA` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pembelian_bahan`
--

INSERT INTO `pembelian_bahan` (`ID_PEMBELIAN`, `ID_SUPPLIER`, `ID_OWNER`, `TANGGAL_BELI`, `TOTAL_BIAYA`) VALUES
('PB01', 'SUP01', 'OWN01', '2025-01-01', 5000000),
('PB02', 'SUP02', 'OWN01', '2025-01-05', 3000000),
('PB03', 'SUP03', 'OWN01', '2025-01-10', 2000000),
('PB04', 'SUP04', 'OWN01', '2025-01-15', 4500000),
('PB05', 'SUP05', 'OWN01', '2025-01-20', 3500000);

-- --------------------------------------------------------

--
-- Table structure for table `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `ID_PENGELUARAN` varchar(10) NOT NULL,
  `ID_OWNER` varchar(10) DEFAULT NULL,
  `TANGGAL_PENGELUARAN` date NOT NULL,
  `JENIS_PENGELUARAN` varchar(50) NOT NULL,
  `JUMLAH_PENGELUARAN` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengeluaran`
--

INSERT INTO `pengeluaran` (`ID_PENGELUARAN`, `ID_OWNER`, `TANGGAL_PENGELUARAN`, `JENIS_PENGELUARAN`, `JUMLAH_PENGELUARAN`) VALUES
('PNG01', 'OWN01', '2025-01-01', 'Beli Bahan', 3000000),
('PNG02', 'OWN01', '2025-01-05', 'Upah Penjahit', 2000000),
('PNG03', 'OWN01', '2025-01-10', 'Transport', 500000),
('PNG04', 'OWN01', '2025-01-15', 'Listrik', 750000),
('PNG05', 'OWN01', '2025-01-20', 'Perawatan', 600000);

-- --------------------------------------------------------

--
-- Table structure for table `penggajian`
--

CREATE TABLE `penggajian` (
  `ID_GAJI` varchar(10) NOT NULL,
  `ID_PENJAHIT` varchar(10) DEFAULT NULL,
  `ID_PRODUKSI` varchar(10) DEFAULT NULL,
  `TOTAL_UPAH` int(11) DEFAULT NULL,
  `STATUS_GAJI` enum('Sudah Dibayar','Belum Dibayar') DEFAULT 'Belum Dibayar',
  `BUKTI_BAYAR` varchar(255) DEFAULT NULL,
  `TANGGAL_BAYAR` datetime DEFAULT NULL,
  `STATUS_TERIMA` enum('Belum','Diterima') DEFAULT 'Belum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penggajian`
--

INSERT INTO `penggajian` (`ID_GAJI`, `ID_PENJAHIT`, `ID_PRODUKSI`, `TOTAL_UPAH`, `STATUS_GAJI`, `BUKTI_BAYAR`, `TANGGAL_BAYAR`, `STATUS_TERIMA`) VALUES
('', NULL, 'PRK01', NULL, 'Belum Dibayar', 'Screenshot 2024-08-20 220728.png', NULL, 'Diterima');

-- --------------------------------------------------------

--
-- Table structure for table `penjahit`
--

CREATE TABLE `penjahit` (
  `ID_PENJAHIT` varchar(10) NOT NULL,
  `NAMA_PENJAHIT` varchar(50) NOT NULL,
  `KEAHLIAN` varchar(25) NOT NULL,
  `UPAH_PER_UNIT` decimal(10,0) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penjahit`
--

INSERT INTO `penjahit` (`ID_PENJAHIT`, `NAMA_PENJAHIT`, `KEAHLIAN`, `UPAH_PER_UNIT`, `username`, `password`) VALUES
('PJT01', 'Ahmad', 'Jahit Halus', 10000, 'jahit1', '123'),
('PJT02', 'Rina', 'Bordir', 15000, 'jahit2', '123'),
('PJT03', 'Agus', 'Jahit Massal', 8000, 'jahit3', '123'),
('PJT04', 'Dewi', 'Finishing', 7000, 'jahit4', '123'),
('PJT05', 'Tono', 'Potong Kain', 8500, 'jahit5', '123');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `ID_PESANAN` varchar(10) NOT NULL,
  `ID_OWNER` varchar(10) DEFAULT NULL,
  `ID_PELANGGAN` varchar(10) DEFAULT NULL,
  `WAKTU_PESAN` datetime NOT NULL,
  `TOTAL_HARGA` decimal(10,0) NOT NULL,
  `STATUS` varchar(10) NOT NULL,
  `ID_PENJAHIT` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pesanan`
--

INSERT INTO `pesanan` (`ID_PESANAN`, `ID_OWNER`, `ID_PELANGGAN`, `WAKTU_PESAN`, `TOTAL_HARGA`, `STATUS`, `ID_PENJAHIT`) VALUES
('PSN01', 'OWN01', 'PLG01', '2025-02-01 00:00:00', 2175000, 'Pending', 'PJT05'),
('PSN02', 'OWN01', 'PLG02', '2025-02-03 00:00:00', 3000000, 'Proses', 'PJT05'),
('PSN03', 'OWN02', 'PLG03', '2025-02-05 00:00:00', 2500000, 'Selesai', NULL),
('PSN04', 'OWN03', 'PLG04', '2025-02-07 00:00:00', 4500000, 'Pending', 'PJT03'),
('PSN06', 'OWN1', 'PLG06', '2025-12-27 03:52:15', 1400000, 'Proses', 'PJT04'),
('PSN07', 'OWN1', 'PLG06', '2025-12-28 00:14:29', 2800000, 'Pending', NULL),
('PSN08', 'OWN1', 'PLG06', '2025-12-28 00:26:28', 300000, 'Pending', NULL),
('PSN09', 'OWN1', 'PLG01', '2026-04-02 09:26:21', 2800000, 'Pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `ID_PRODUK` varchar(10) NOT NULL COMMENT 'ID unik untuk produk',
  `NAMA_PRODUK` varchar(25) NOT NULL COMMENT 'Nama produk',
  `JENIS_BAHAN` varchar(25) NOT NULL COMMENT 'Jenis bahan yang digunakan',
  `UKURAN` varchar(100) NOT NULL COMMENT 'Ukuran produk',
  `HARGA` decimal(10,2) NOT NULL COMMENT 'Harga produk'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`ID_PRODUK`, `NAMA_PRODUK`, `JENIS_BAHAN`, `UKURAN`, `HARGA`) VALUES
('PRD01', 'Busana Muslim Anak Pria', 'Drill, Golden Mela', 'L, XL, L2', 145000.00),
('PRD02', 'Seragam TK', 'Katun', 'M, L, XL, XXL', 60000.00),
('PRD03', 'Seragam SD', 'Katun', 'M,L,XL,XXL,2L', 70000.00),
('PRD04', 'Baju Koko Dewasa', 'Toyobo', 'M, L, XL', 140000.00),
('PRD08', 'Krudung SMP Bordir', 'katun', 'ALL SIZE', 30000.00);

-- --------------------------------------------------------

--
-- Table structure for table `produksi`
--

CREATE TABLE `produksi` (
  `ID_PRODUKSI` varchar(10) NOT NULL COMMENT 'ID unik untuk produksi',
  `ID_PRODUK` varchar(10) DEFAULT NULL COMMENT 'Relasi ke tabel PRODUK',
  `ID_PENJAHIT` varchar(10) DEFAULT NULL COMMENT 'Relasi ke tabel PENJAHIT',
  `STATUS_PRODUKSI` varchar(50) DEFAULT 'Pending',
  `KETERANGAN` text DEFAULT NULL,
  `TANGGAL_MULAI` date NOT NULL COMMENT 'Tanggal mulai produksi',
  `TANGGAL_SELESAI` date NOT NULL COMMENT 'Tanggal selesai produksi',
  `JUMLAH_DIPRODUKSI` int(11) NOT NULL COMMENT 'Jumlah barang diproduksi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produksi`
--

INSERT INTO `produksi` (`ID_PRODUKSI`, `ID_PRODUK`, `ID_PENJAHIT`, `STATUS_PRODUKSI`, `KETERANGAN`, `TANGGAL_MULAI`, `TANGGAL_SELESAI`, `JUMLAH_DIPRODUKSI`) VALUES
('PRD01', 'PROD01', NULL, 'Pending', NULL, '0000-00-00', '0000-00-00', 0),
('PRD10', 'PROD02', NULL, 'Proses', NULL, '0000-00-00', '0000-00-00', 0),
('PRK01', 'PRD01', 'PJT01', 'Selesai', 'sakit', '2025-02-01', '2025-02-05', 10),
('PRK02', 'PRD02', 'PJT02', 'Pending', NULL, '2025-02-03', '2025-02-07', 20),
('PRK03', 'PRD03', 'PJT03', 'Pending', NULL, '2025-02-05', '2025-02-10', 15),
('PRK04', 'PRD04', 'PJT04', 'Pending', NULL, '2025-02-07', '2025-02-12', 8),
('PRK05', 'PRD05', 'PJT05', 'Pending', NULL, '2025-02-09', '2025-02-14', 12);

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `ID_SUPPLIER` varchar(10) NOT NULL COMMENT 'ID unik untuk supplier',
  `NAMA_SUPPLIER` varchar(50) NOT NULL COMMENT 'Nama supplier',
  `ALAMAT_SUPPLIER` varchar(100) NOT NULL COMMENT 'Alamat lengkap supplier',
  `NO_TELP` varchar(15) NOT NULL COMMENT 'Nomor telepon supplier'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`ID_SUPPLIER`, `NAMA_SUPPLIER`, `ALAMAT_SUPPLIER`, `NO_TELP`) VALUES
('SUP01', 'PT Kain Jaya', 'Bandung', '022111111'),
('SUP02', 'CV Tekstil Makmur', 'Jakarta', '021222222'),
('SUP03', 'UD Benang Indah', 'Solo', '027133333'),
('SUP04', 'PT Drill Utama', 'Surabaya', '031444444'),
('SUP05', 'CV Golden Textile', 'Malang', '034155555'),
('SUP06', 'Batik Cemani', 'Pasar kembang jepun', '0889230892'),
('SUP07', 'Distributor Rajut Jadi Bandung', 'bandung', '08892308921');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_stok_produksi`
-- (See below for the actual view)
--
CREATE TABLE `view_stok_produksi` (
`ID_PRODUK` varchar(10)
,`NAMA_PRODUK` varchar(25)
,`JENIS_BAHAN` varchar(25)
,`UKURAN` varchar(100)
);

-- --------------------------------------------------------

--
-- Structure for view `view_stok_produksi`
--
DROP TABLE IF EXISTS `view_stok_produksi`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_stok_produksi`  AS SELECT `produk`.`ID_PRODUK` AS `ID_PRODUK`, `produk`.`NAMA_PRODUK` AS `NAMA_PRODUK`, `produk`.`JENIS_BAHAN` AS `JENIS_BAHAN`, `produk`.`UKURAN` AS `UKURAN` FROM `produk` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `aset`
--
ALTER TABLE `aset`
  ADD PRIMARY KEY (`ID_ASET`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id_log`);

--
-- Indexes for table `bahan_baku`
--
ALTER TABLE `bahan_baku`
  ADD PRIMARY KEY (`ID_BAHAN`);

--
-- Indexes for table `detail_pembelian`
--
ALTER TABLE `detail_pembelian`
  ADD PRIMARY KEY (`ID_DETAIL_BELI`);

--
-- Indexes for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`ID_DETAIL`);

--
-- Indexes for table `log_recovery_system`
--
ALTER TABLE `log_recovery_system`
  ADD PRIMARY KEY (`id_recovery`);

--
-- Indexes for table `owner`
--
ALTER TABLE `owner`
  ADD PRIMARY KEY (`ID_OWNER`);

--
-- Indexes for table `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`ID_PELANGGAN`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_customer_nama` (`NAMA_PELANGGAN`),
  ADD KEY `idx_nama_pelanggan` (`NAMA_PELANGGAN`),
  ADD KEY `NAMA_PELANGGAN` (`NAMA_PELANGGAN`);

--
-- Indexes for table `pemasukan`
--
ALTER TABLE `pemasukan`
  ADD PRIMARY KEY (`ID_PEMASUKAN`);

--
-- Indexes for table `pembelian_bahan`
--
ALTER TABLE `pembelian_bahan`
  ADD PRIMARY KEY (`ID_PEMBELIAN`);

--
-- Indexes for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`ID_PENGELUARAN`);

--
-- Indexes for table `penggajian`
--
ALTER TABLE `penggajian`
  ADD PRIMARY KEY (`ID_GAJI`);

--
-- Indexes for table `penjahit`
--
ALTER TABLE `penjahit`
  ADD PRIMARY KEY (`ID_PENJAHIT`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`ID_PESANAN`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`ID_PRODUK`);

--
-- Indexes for table `produksi`
--
ALTER TABLE `produksi`
  ADD PRIMARY KEY (`ID_PRODUKSI`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`ID_SUPPLIER`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `ID_DETAIL` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `log_recovery_system`
--
ALTER TABLE `log_recovery_system`
  MODIFY `id_recovery` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
