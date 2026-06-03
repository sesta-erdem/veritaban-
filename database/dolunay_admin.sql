-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 06, 2026 at 10:46 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dolunay_admin`
--

DELIMITER $$
--
-- Procedures
--
CREATE PROCEDURE `sesta_admin_eposta_var_mi` (IN `p_eposta` VARCHAR(255), IN `p_ignore_id` INT)   BEGIN
    SELECT COUNT(*) AS adet
    FROM admin_kullanicilar
    WHERE eposta = p_eposta
      AND (p_ignore_id IS NULL OR kullanici_id != p_ignore_id);
END$$

CREATE PROCEDURE `sesta_admin_giris_kullanici_getir` (IN `p_eposta` VARCHAR(255))   BEGIN
    SELECT kullanici_id, ad_soyad, eposta, sifre_hash, rutbe, sil, failed_attempts, lock_until, son_giris_tarihi, mfa_enabled
    FROM admin_kullanicilar
    WHERE eposta = p_eposta AND sil = 2
    LIMIT 1;
END$$

CREATE PROCEDURE `sesta_admin_kullanicilar_hepsi` ()   BEGIN
    SELECT kullanici_id, ad_soyad, eposta, rutbe, sil, kayit_tarihi, son_giris_tarihi
    FROM admin_kullanicilar
    ORDER BY kullanici_id DESC;
END$$

CREATE PROCEDURE `sesta_admin_kullanicilar_sayfa` (IN `p_page` INT, IN `p_limit` INT)   BEGIN
    DECLARE v_page INT DEFAULT IFNULL(p_page, 1);
    DECLARE v_limit INT DEFAULT IFNULL(p_limit, 25);
    DECLARE v_offset INT DEFAULT 0;

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_limit < 1 THEN
        SET v_limit = 25;
    END IF;

    IF v_limit > 100 THEN
        SET v_limit = 100;
    END IF;

    SET v_offset = (v_page - 1) * v_limit;

    SELECT COUNT(*) AS total
    FROM admin_kullanicilar;

    SELECT kullanici_id, ad_soyad, eposta, rutbe, sil, kayit_tarihi, son_giris_tarihi
    FROM admin_kullanicilar
    ORDER BY kullanici_id DESC
    LIMIT v_limit OFFSET v_offset;
END$$

CREATE PROCEDURE `sesta_admin_kullanici_basarisiz_giris` (IN `p_kullanici_id` INT, IN `p_failed_attempts` SMALLINT UNSIGNED, IN `p_lock_until` DATETIME)   BEGIN
    UPDATE admin_kullanicilar
    SET failed_attempts = p_failed_attempts,
        lock_until = p_lock_until
    WHERE kullanici_id = p_kullanici_id;
END$$

CREATE PROCEDURE `sesta_admin_kullanici_ekle` (IN `p_ad_soyad` VARCHAR(255), IN `p_eposta` VARCHAR(255), IN `p_sifre_hash` VARCHAR(255), IN `p_rutbe` TINYINT, IN `p_sil` TINYINT)   BEGIN
    INSERT INTO admin_kullanicilar (ad_soyad, eposta, sifre_hash, rutbe, sil)
    VALUES (p_ad_soyad, p_eposta, p_sifre_hash, p_rutbe, p_sil);

    SELECT LAST_INSERT_ID() AS kullanici_id;
END$$

CREATE PROCEDURE `sesta_admin_kullanici_getir` (IN `p_kullanici_id` INT)   BEGIN
    SELECT kullanici_id, ad_soyad, eposta, rutbe, sil, kayit_tarihi, son_giris_tarihi
    FROM admin_kullanicilar
    WHERE kullanici_id = p_kullanici_id
    LIMIT 1;
END$$

CREATE PROCEDURE `sesta_admin_kullanici_giris_sifirla` (IN `p_kullanici_id` INT)   BEGIN
    UPDATE admin_kullanicilar
    SET failed_attempts = 0,
        lock_until = NULL,
        son_giris_tarihi = NOW()
    WHERE kullanici_id = p_kullanici_id;
END$$

CREATE PROCEDURE `sesta_admin_kullanici_guncelle` (IN `p_kullanici_id` INT, IN `p_ad_soyad` VARCHAR(255), IN `p_eposta` VARCHAR(255), IN `p_rutbe` TINYINT, IN `p_sil` TINYINT)   BEGIN
    UPDATE admin_kullanicilar
    SET ad_soyad = p_ad_soyad,
        eposta = p_eposta,
        rutbe = p_rutbe,
        sil = p_sil
    WHERE kullanici_id = p_kullanici_id;
END$$

CREATE PROCEDURE `sesta_admin_kullanici_kilit_temizle` (IN `p_kullanici_id` INT)   BEGIN
    UPDATE admin_kullanicilar
    SET failed_attempts = 0,
        lock_until = NULL
    WHERE kullanici_id = p_kullanici_id;
END$$

CREATE PROCEDURE `sesta_admin_kullanici_sifreli_guncelle` (IN `p_kullanici_id` INT, IN `p_ad_soyad` VARCHAR(255), IN `p_eposta` VARCHAR(255), IN `p_sifre_hash` VARCHAR(255), IN `p_rutbe` TINYINT, IN `p_sil` TINYINT)   BEGIN
    UPDATE admin_kullanicilar
    SET ad_soyad = p_ad_soyad,
        eposta = p_eposta,
        sifre_hash = p_sifre_hash,
        rutbe = p_rutbe,
        sil = p_sil
    WHERE kullanici_id = p_kullanici_id;
END$$

CREATE PROCEDURE `sesta_admin_kullanici_sifre_hash_guncelle` (IN `p_kullanici_id` INT, IN `p_sifre_hash` VARCHAR(255))   BEGIN
    UPDATE admin_kullanicilar
    SET sifre_hash = p_sifre_hash
    WHERE kullanici_id = p_kullanici_id;
END$$

CREATE PROCEDURE `sesta_blog_ekle` (IN `p_baslik` VARCHAR(255), IN `p_slug` VARCHAR(255), IN `p_excerpt` TEXT, IN `p_kapak_gorseli` VARCHAR(255), IN `p_yazar` VARCHAR(255), IN `p_yayin_tarihi` DATE, IN `p_etiketler` TEXT, IN `p_icerik` LONGTEXT, IN `p_seo_title` VARCHAR(255), IN `p_seo_desc` TEXT, IN `p_durum` TINYINT, IN `p_ekleyen` INT)   BEGIN
    INSERT INTO blog_yazilari (
        baslik, slug, excerpt, kapak_gorseli, yazar, yayin_tarihi,
        etiketler, icerik, seo_title, seo_desc, durum, ekleyen
    ) VALUES (
        p_baslik, p_slug, p_excerpt, p_kapak_gorseli, p_yazar, p_yayin_tarihi,
        p_etiketler, p_icerik, p_seo_title, p_seo_desc, p_durum, p_ekleyen
    );

    SELECT LAST_INSERT_ID() AS yazi_id;
END$$

CREATE PROCEDURE `sesta_blog_getir` (IN `p_yazi_id` INT)   BEGIN
    SELECT *
    FROM blog_yazilari
    WHERE yazi_id = p_yazi_id
      AND sil = 2
    LIMIT 1;
END$$

CREATE PROCEDURE `sesta_blog_guncelle` (IN `p_yazi_id` INT, IN `p_baslik` VARCHAR(255), IN `p_slug` VARCHAR(255), IN `p_excerpt` TEXT, IN `p_kapak_gorseli` VARCHAR(255), IN `p_yazar` VARCHAR(255), IN `p_yayin_tarihi` DATE, IN `p_etiketler` TEXT, IN `p_icerik` LONGTEXT, IN `p_seo_title` VARCHAR(255), IN `p_seo_desc` TEXT, IN `p_durum` TINYINT)   BEGIN
    UPDATE blog_yazilari
    SET baslik = p_baslik,
        slug = p_slug,
        excerpt = p_excerpt,
        kapak_gorseli = p_kapak_gorseli,
        yazar = p_yazar,
        yayin_tarihi = p_yayin_tarihi,
        etiketler = p_etiketler,
        icerik = p_icerik,
        seo_title = p_seo_title,
        seo_desc = p_seo_desc,
        durum = p_durum,
        guncelleme_tarihi = NOW()
    WHERE yazi_id = p_yazi_id;
END$$

CREATE PROCEDURE `sesta_blog_hepsi` ()   BEGIN
    SELECT *
    FROM blog_yazilari
    WHERE sil = 2
    ORDER BY yayin_tarihi DESC, yazi_id DESC;
END$$

CREATE PROCEDURE `sesta_blog_sayfa` (IN `p_page` INT, IN `p_limit` INT)   BEGIN
    DECLARE v_page INT DEFAULT IFNULL(p_page, 1);
    DECLARE v_limit INT DEFAULT IFNULL(p_limit, 25);
    DECLARE v_offset INT DEFAULT 0;

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_limit < 1 THEN
        SET v_limit = 25;
    END IF;

    IF v_limit > 100 THEN
        SET v_limit = 100;
    END IF;

    SET v_offset = (v_page - 1) * v_limit;

    SELECT COUNT(*) AS total
    FROM blog_yazilari
    WHERE sil = 2;

    SELECT *
    FROM blog_yazilari
    WHERE sil = 2
    ORDER BY yayin_tarihi DESC, yazi_id DESC
    LIMIT v_limit OFFSET v_offset;
END$$

CREATE PROCEDURE `sesta_blog_sil` (IN `p_yazi_id` INT)   BEGIN
    UPDATE blog_yazilari
    SET sil = 1
    WHERE yazi_id = p_yazi_id;
END$$

CREATE PROCEDURE `sesta_blog_slug_var_mi` (IN `p_slug` VARCHAR(255), IN `p_ignore_id` INT)   BEGIN
    SELECT COUNT(*) AS adet
    FROM blog_yazilari
    WHERE slug = p_slug
      AND sil = 2
      AND (p_ignore_id IS NULL OR yazi_id != p_ignore_id);
END$$

CREATE PROCEDURE `sesta_dashboard_sayilari` ()   BEGIN
    SELECT
        (SELECT COUNT(*) FROM projeler WHERE sil = 2) AS projeler,
        (SELECT COUNT(*) FROM hizmetler WHERE sil = 2) AS hizmetler,
        (SELECT COUNT(*) FROM blog_yazilari WHERE sil = 2) AS blog,
        (SELECT COUNT(*) FROM iletisim_talepleri WHERE sil = 2) AS talepler;
END$$

CREATE PROCEDURE `sesta_faq_ekle` (IN `p_sirasi` INT, IN `p_soru` VARCHAR(255), IN `p_cevap` TEXT, IN `p_durum` TINYINT)   BEGIN
    INSERT INTO faq (sirasi, soru, cevap, durum)
    VALUES (p_sirasi, p_soru, p_cevap, p_durum);

    SELECT LAST_INSERT_ID() AS faq_id;
END$$

CREATE PROCEDURE `sesta_faq_getir` (IN `p_faq_id` INT)   BEGIN
    SELECT *
    FROM faq
    WHERE faq_id = p_faq_id
      AND sil = 2
    LIMIT 1;
END$$

CREATE PROCEDURE `sesta_faq_guncelle` (IN `p_faq_id` INT, IN `p_sirasi` INT, IN `p_soru` VARCHAR(255), IN `p_cevap` TEXT, IN `p_durum` TINYINT)   BEGIN
    UPDATE faq
    SET sirasi = p_sirasi,
        soru = p_soru,
        cevap = p_cevap,
        durum = p_durum
    WHERE faq_id = p_faq_id;
END$$

CREATE PROCEDURE `sesta_faq_hepsi` ()   BEGIN
    SELECT *
    FROM faq
    WHERE sil = 2
    ORDER BY sirasi ASC, faq_id DESC;
END$$

CREATE PROCEDURE `sesta_faq_sayfa` (IN `p_page` INT, IN `p_limit` INT)   BEGIN
    DECLARE v_page INT DEFAULT IFNULL(p_page, 1);
    DECLARE v_limit INT DEFAULT IFNULL(p_limit, 25);
    DECLARE v_offset INT DEFAULT 0;

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_limit < 1 THEN
        SET v_limit = 25;
    END IF;

    IF v_limit > 100 THEN
        SET v_limit = 100;
    END IF;

    SET v_offset = (v_page - 1) * v_limit;

    SELECT COUNT(*) AS total
    FROM faq
    WHERE sil = 2;

    SELECT *
    FROM faq
    WHERE sil = 2
    ORDER BY sirasi ASC, faq_id DESC
    LIMIT v_limit OFFSET v_offset;
END$$

CREATE PROCEDURE `sesta_faq_sil` (IN `p_faq_id` INT)   BEGIN
    UPDATE faq
    SET sil = 1
    WHERE faq_id = p_faq_id;
END$$

CREATE PROCEDURE `sesta_hero_ekle` (IN `p_sirasi` INT, IN `p_eyebrow` VARCHAR(255), IN `p_baslik` VARCHAR(255), IN `p_alt_baslik` VARCHAR(255), IN `p_aciklama` TEXT, IN `p_gorsel` VARCHAR(255), IN `p_cta_bir_metin` VARCHAR(100), IN `p_cta_bir_link` VARCHAR(255), IN `p_cta_iki_metin` VARCHAR(100), IN `p_cta_iki_link` VARCHAR(255), IN `p_durum` TINYINT, IN `p_ekleyen` INT)   BEGIN
    INSERT INTO hero_slaytlar (
        sirasi, eyebrow, baslik, alt_baslik, aciklama, gorsel,
        cta_bir_metin, cta_bir_link, cta_iki_metin, cta_iki_link, durum, ekleyen
    ) VALUES (
        p_sirasi, p_eyebrow, p_baslik, p_alt_baslik, p_aciklama, p_gorsel,
        p_cta_bir_metin, p_cta_bir_link, p_cta_iki_metin, p_cta_iki_link, p_durum, p_ekleyen
    );

    SELECT LAST_INSERT_ID() AS hero_id;
END$$

CREATE PROCEDURE `sesta_hero_getir` (IN `p_hero_id` INT)   BEGIN
    SELECT *
    FROM hero_slaytlar
    WHERE hero_id = p_hero_id
      AND sil = 2
    LIMIT 1;
END$$

CREATE PROCEDURE `sesta_hero_guncelle` (IN `p_hero_id` INT, IN `p_sirasi` INT, IN `p_eyebrow` VARCHAR(255), IN `p_baslik` VARCHAR(255), IN `p_alt_baslik` VARCHAR(255), IN `p_aciklama` TEXT, IN `p_gorsel` VARCHAR(255), IN `p_cta_bir_metin` VARCHAR(100), IN `p_cta_bir_link` VARCHAR(255), IN `p_cta_iki_metin` VARCHAR(100), IN `p_cta_iki_link` VARCHAR(255), IN `p_durum` TINYINT)   BEGIN
    UPDATE hero_slaytlar
    SET sirasi = p_sirasi,
        eyebrow = p_eyebrow,
        baslik = p_baslik,
        alt_baslik = p_alt_baslik,
        aciklama = p_aciklama,
        gorsel = p_gorsel,
        cta_bir_metin = p_cta_bir_metin,
        cta_bir_link = p_cta_bir_link,
        cta_iki_metin = p_cta_iki_metin,
        cta_iki_link = p_cta_iki_link,
        durum = p_durum,
        guncelleme_tarihi = NOW()
    WHERE hero_id = p_hero_id;
END$$

CREATE PROCEDURE `sesta_hero_hepsi` ()   BEGIN
    SELECT *
    FROM hero_slaytlar
    WHERE sil = 2
    ORDER BY sirasi ASC, hero_id DESC;
END$$

CREATE PROCEDURE `sesta_hero_sayfa` (IN `p_page` INT, IN `p_limit` INT)   BEGIN
    DECLARE v_page INT DEFAULT IFNULL(p_page, 1);
    DECLARE v_limit INT DEFAULT IFNULL(p_limit, 25);
    DECLARE v_offset INT DEFAULT 0;

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_limit < 1 THEN
        SET v_limit = 25;
    END IF;

    IF v_limit > 100 THEN
        SET v_limit = 100;
    END IF;

    SET v_offset = (v_page - 1) * v_limit;

    SELECT COUNT(*) AS total
    FROM hero_slaytlar
    WHERE sil = 2;

    SELECT *
    FROM hero_slaytlar
    WHERE sil = 2
    ORDER BY sirasi ASC, hero_id DESC
    LIMIT v_limit OFFSET v_offset;
END$$

CREATE PROCEDURE `sesta_hero_sil` (IN `p_hero_id` INT)   BEGIN
    UPDATE hero_slaytlar
    SET sil = 1
    WHERE hero_id = p_hero_id;
END$$

CREATE PROCEDURE `sesta_hizmetler_hepsi` ()   BEGIN
    SELECT *
    FROM hizmetler
    WHERE sil = 2
    ORDER BY sirasi ASC, hizmet_id DESC;
END$$

CREATE PROCEDURE `sesta_hizmetler_sayfa` (IN `p_page` INT, IN `p_limit` INT)   BEGIN
    DECLARE v_page INT DEFAULT IFNULL(p_page, 1);
    DECLARE v_limit INT DEFAULT IFNULL(p_limit, 25);
    DECLARE v_offset INT DEFAULT 0;

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_limit < 1 THEN
        SET v_limit = 25;
    END IF;

    IF v_limit > 100 THEN
        SET v_limit = 100;
    END IF;

    SET v_offset = (v_page - 1) * v_limit;

    SELECT COUNT(*) AS total
    FROM hizmetler
    WHERE sil = 2;

    SELECT *
    FROM hizmetler
    WHERE sil = 2
    ORDER BY sirasi ASC, hizmet_id DESC
    LIMIT v_limit OFFSET v_offset;
END$$

CREATE PROCEDURE `sesta_hizmet_ekle` (IN `p_sirasi` INT, IN `p_baslik` VARCHAR(255), IN `p_slug` VARCHAR(255), IN `p_kisa_ozet` TEXT, IN `p_detay` TEXT, IN `p_gorsel` VARCHAR(255), IN `p_ne_yapiyoruz` TEXT, IN `p_hangi_ciktilar` TEXT, IN `p_kimler_icin` TEXT, IN `p_surece_katkisi` TEXT, IN `p_durum` TINYINT, IN `p_ekleyen` INT)   BEGIN
    INSERT INTO hizmetler (
        sirasi, baslik, slug, kisa_ozet, detay, gorsel, ne_yapiyoruz,
        hangi_ciktilar, kimler_icin, surece_katkisi, durum, ekleyen
    ) VALUES (
        p_sirasi, p_baslik, p_slug, p_kisa_ozet, p_detay, p_gorsel, p_ne_yapiyoruz,
        p_hangi_ciktilar, p_kimler_icin, p_surece_katkisi, p_durum, p_ekleyen
    );

    SELECT LAST_INSERT_ID() AS hizmet_id;
END$$

CREATE PROCEDURE `sesta_hizmet_getir` (IN `p_hizmet_id` INT)   BEGIN
    SELECT *
    FROM hizmetler
    WHERE hizmet_id = p_hizmet_id
      AND sil = 2
    LIMIT 1;
END$$

CREATE PROCEDURE `sesta_hizmet_guncelle` (IN `p_hizmet_id` INT, IN `p_sirasi` INT, IN `p_baslik` VARCHAR(255), IN `p_slug` VARCHAR(255), IN `p_kisa_ozet` TEXT, IN `p_detay` TEXT, IN `p_gorsel` VARCHAR(255), IN `p_ne_yapiyoruz` TEXT, IN `p_hangi_ciktilar` TEXT, IN `p_kimler_icin` TEXT, IN `p_surece_katkisi` TEXT, IN `p_durum` TINYINT)   BEGIN
    UPDATE hizmetler
    SET sirasi = p_sirasi,
        baslik = p_baslik,
        slug = p_slug,
        kisa_ozet = p_kisa_ozet,
        detay = p_detay,
        gorsel = p_gorsel,
        ne_yapiyoruz = p_ne_yapiyoruz,
        hangi_ciktilar = p_hangi_ciktilar,
        kimler_icin = p_kimler_icin,
        surece_katkisi = p_surece_katkisi,
        durum = p_durum,
        guncelleme_tarihi = NOW()
    WHERE hizmet_id = p_hizmet_id;
END$$

CREATE PROCEDURE `sesta_hizmet_sil` (IN `p_hizmet_id` INT)   BEGIN
    UPDATE hizmetler
    SET sil = 1
    WHERE hizmet_id = p_hizmet_id;
END$$

CREATE PROCEDURE `sesta_hizmet_slug_var_mi` (IN `p_slug` VARCHAR(255), IN `p_ignore_id` INT)   BEGIN
    SELECT COUNT(*) AS adet
    FROM hizmetler
    WHERE slug = p_slug
      AND sil = 2
      AND (p_ignore_id IS NULL OR hizmet_id != p_ignore_id);
END$$

CREATE PROCEDURE `sesta_hizmet_teslimleri_getir` (IN `p_hizmet_id` INT)   BEGIN
    SELECT *
    FROM hizmet_teslimleri
    WHERE hizmet_id = p_hizmet_id
      AND sil = 2
    ORDER BY sirasi ASC, teslim_id ASC;
END$$

CREATE PROCEDURE `sesta_hizmet_teslimleri_temizle` (IN `p_hizmet_id` INT)   BEGIN
    UPDATE hizmet_teslimleri
    SET sil = 1
    WHERE hizmet_id = p_hizmet_id;
END$$

CREATE PROCEDURE `sesta_hizmet_teslim_ekle` (IN `p_hizmet_id` INT, IN `p_sirasi` INT, IN `p_baslik` VARCHAR(255), IN `p_aciklama` TEXT)   BEGIN
    INSERT INTO hizmet_teslimleri (hizmet_id, sirasi, baslik, aciklama, durum, sil)
    VALUES (p_hizmet_id, p_sirasi, p_baslik, p_aciklama, 1, 2);
END$$

CREATE PROCEDURE `sesta_medya_aktif_kategorilere_gore` (IN `p_kategoriler` TEXT)   BEGIN
    SELECT *
    FROM medya
    WHERE sil = 2
      AND durum = 1
      AND (
        p_kategoriler IS NULL
        OR p_kategoriler = ''
        OR FIND_IN_SET(kategori, p_kategoriler) > 0
      )
    ORDER BY kategori ASC, dosya_adi ASC, medya_id DESC;
END$$

CREATE PROCEDURE `sesta_medya_ekle` (IN `p_dosya_adi` VARCHAR(255), IN `p_dosya_yolu` VARCHAR(255), IN `p_alt_metin` VARCHAR(255), IN `p_kategori` VARCHAR(100), IN `p_durum` TINYINT, IN `p_yukleyen` INT)   BEGIN
    INSERT INTO medya (dosya_adi, dosya_yolu, alt_metin, kategori, durum, yukleyen)
    VALUES (p_dosya_adi, p_dosya_yolu, p_alt_metin, p_kategori, p_durum, p_yukleyen);

    SELECT LAST_INSERT_ID() AS medya_id;
END$$

CREATE PROCEDURE `sesta_medya_filtreli` (IN `p_kategori` VARCHAR(100))   BEGIN
    SELECT *
    FROM medya
    WHERE sil = 2
      AND (p_kategori IS NULL OR p_kategori = '' OR kategori = p_kategori)
    ORDER BY medya_id DESC;
END$$

CREATE PROCEDURE `sesta_medya_filtreli_sayfa` (IN `p_kategori` VARCHAR(100), IN `p_page` INT, IN `p_limit` INT)   BEGIN
    DECLARE v_page INT DEFAULT IFNULL(p_page, 1);
    DECLARE v_limit INT DEFAULT IFNULL(p_limit, 25);
    DECLARE v_offset INT DEFAULT 0;

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_limit < 1 THEN
        SET v_limit = 25;
    END IF;

    IF v_limit > 100 THEN
        SET v_limit = 100;
    END IF;

    SET v_offset = (v_page - 1) * v_limit;

    SELECT COUNT(*) AS total
    FROM medya
    WHERE sil = 2
      AND (p_kategori IS NULL OR p_kategori = '' OR kategori = p_kategori);

    SELECT *
    FROM medya
    WHERE sil = 2
      AND (p_kategori IS NULL OR p_kategori = '' OR kategori = p_kategori)
    ORDER BY medya_id DESC
    LIMIT v_limit OFFSET v_offset;
END$$

CREATE PROCEDURE `sesta_medya_getir` (IN `p_medya_id` INT)   BEGIN
    SELECT *
    FROM medya
    WHERE medya_id = p_medya_id
      AND sil = 2
    LIMIT 1;
END$$

CREATE PROCEDURE `sesta_medya_guncelle` (IN `p_medya_id` INT, IN `p_dosya_adi` VARCHAR(255), IN `p_dosya_yolu` VARCHAR(255), IN `p_alt_metin` VARCHAR(255), IN `p_kategori` VARCHAR(100), IN `p_durum` TINYINT)   BEGIN
    UPDATE medya
    SET dosya_adi = p_dosya_adi,
        dosya_yolu = p_dosya_yolu,
        alt_metin = p_alt_metin,
        kategori = p_kategori,
        durum = p_durum
    WHERE medya_id = p_medya_id;
END$$

CREATE PROCEDURE `sesta_medya_hepsi` ()   BEGIN
    SELECT *
    FROM medya
    WHERE sil = 2
    ORDER BY medya_id DESC;
END$$

CREATE PROCEDURE `sesta_medya_kategorileri` ()   BEGIN
    SELECT DISTINCT kategori
    FROM medya
    WHERE sil = 2
      AND kategori IS NOT NULL
      AND kategori != ''
    ORDER BY kategori ASC;
END$$

CREATE PROCEDURE `sesta_medya_sil` (IN `p_medya_id` INT)   BEGIN
    UPDATE medya
    SET sil = 1
    WHERE medya_id = p_medya_id;
END$$

CREATE PROCEDURE `sesta_projeler_hepsi` ()   BEGIN
    SELECT *
    FROM projeler
    WHERE sil = 2
    ORDER BY sirasi ASC, proje_id DESC;
END$$

CREATE PROCEDURE `sesta_projeler_sayfa` (IN `p_page` INT, IN `p_limit` INT)   BEGIN
    DECLARE v_page INT DEFAULT IFNULL(p_page, 1);
    DECLARE v_limit INT DEFAULT IFNULL(p_limit, 25);
    DECLARE v_offset INT DEFAULT 0;

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_limit < 1 THEN
        SET v_limit = 25;
    END IF;

    IF v_limit > 100 THEN
        SET v_limit = 100;
    END IF;

    SET v_offset = (v_page - 1) * v_limit;

    SELECT COUNT(*) AS total
    FROM projeler
    WHERE sil = 2;

    SELECT *
    FROM projeler
    WHERE sil = 2
    ORDER BY sirasi ASC, proje_id DESC
    LIMIT v_limit OFFSET v_offset;
END$$

CREATE PROCEDURE `sesta_proje_ekle` (IN `p_sirasi` INT, IN `p_proje_adi` VARCHAR(255), IN `p_slug` VARCHAR(255), IN `p_kategori` VARCHAR(255), IN `p_sehir` VARCHAR(255), IN `p_yapi_tipi` VARCHAR(255), IN `p_kapsam` TEXT, IN `p_rol` TEXT, IN `p_teknik_cikti` TEXT, IN `p_tonaj` VARCHAR(100), IN `p_metrekare` VARCHAR(100), IN `p_teslim_suresi` VARCHAR(100), IN `p_kisa_ozet` TEXT, IN `p_veri_durumu` VARCHAR(255), IN `p_veri_notu` TEXT, IN `p_one_cikan` TINYINT, IN `p_tasarim_gorseli` VARCHAR(255), IN `p_final_gorseli` VARCHAR(255), IN `p_galeri_gorselleri` LONGTEXT, IN `p_durum` TINYINT, IN `p_ekleyen` INT)   BEGIN
    INSERT INTO projeler (
        sirasi, proje_adi, slug, kategori, sehir, yapi_tipi,
        kapsam, rol, teknik_cikti, tonaj, metrekare,
        teslim_suresi, kisa_ozet, veri_durumu, veri_notu,
        one_cikan, tasarim_gorseli, final_gorseli, galeri_gorselleri, durum, ekleyen
    ) VALUES (
        p_sirasi, p_proje_adi, p_slug, p_kategori, p_sehir, p_yapi_tipi,
        p_kapsam, p_rol, p_teknik_cikti, p_tonaj, p_metrekare,
        p_teslim_suresi, p_kisa_ozet, p_veri_durumu, p_veri_notu,
        p_one_cikan, p_tasarim_gorseli, p_final_gorseli, p_galeri_gorselleri, p_durum, p_ekleyen
    );

    SELECT LAST_INSERT_ID() AS proje_id;
END$$

CREATE PROCEDURE `sesta_proje_getir` (IN `p_proje_id` INT)   BEGIN
    SELECT *
    FROM projeler
    WHERE proje_id = p_proje_id
      AND sil = 2
    LIMIT 1;
END$$

CREATE PROCEDURE `sesta_proje_guncelle` (IN `p_proje_id` INT, IN `p_sirasi` INT, IN `p_proje_adi` VARCHAR(255), IN `p_slug` VARCHAR(255), IN `p_kategori` VARCHAR(255), IN `p_sehir` VARCHAR(255), IN `p_yapi_tipi` VARCHAR(255), IN `p_kapsam` TEXT, IN `p_rol` TEXT, IN `p_teknik_cikti` TEXT, IN `p_tonaj` VARCHAR(100), IN `p_metrekare` VARCHAR(100), IN `p_teslim_suresi` VARCHAR(100), IN `p_kisa_ozet` TEXT, IN `p_veri_durumu` VARCHAR(255), IN `p_veri_notu` TEXT, IN `p_one_cikan` TINYINT, IN `p_tasarim_gorseli` VARCHAR(255), IN `p_final_gorseli` VARCHAR(255), IN `p_galeri_gorselleri` LONGTEXT, IN `p_durum` TINYINT)   BEGIN
    UPDATE projeler
    SET
        sirasi = p_sirasi,
        proje_adi = p_proje_adi,
        slug = p_slug,
        kategori = p_kategori,
        sehir = p_sehir,
        yapi_tipi = p_yapi_tipi,
        kapsam = p_kapsam,
        rol = p_rol,
        teknik_cikti = p_teknik_cikti,
        tonaj = p_tonaj,
        metrekare = p_metrekare,
        teslim_suresi = p_teslim_suresi,
        kisa_ozet = p_kisa_ozet,
        veri_durumu = p_veri_durumu,
        veri_notu = p_veri_notu,
        one_cikan = p_one_cikan,
        tasarim_gorseli = p_tasarim_gorseli,
        final_gorseli = p_final_gorseli,
        galeri_gorselleri = p_galeri_gorselleri,
        durum = p_durum,
        guncelleme_tarihi = NOW()
    WHERE proje_id = p_proje_id;
END$$

CREATE PROCEDURE `sesta_proje_sil` (IN `p_proje_id` INT)   BEGIN
    UPDATE projeler
    SET sil = 1
    WHERE proje_id = p_proje_id;
END$$

CREATE PROCEDURE `sesta_proje_slug_var_mi` (IN `p_slug` VARCHAR(255), IN `p_ignore_id` INT)   BEGIN
    SELECT COUNT(*) AS adet
    FROM projeler
    WHERE slug = p_slug
      AND sil = 2
      AND (p_ignore_id IS NULL OR proje_id != p_ignore_id);
END$$

CREATE PROCEDURE `sesta_site_ayarlari_getir` ()   BEGIN
    SELECT *
    FROM site_ayarlari
    WHERE sil = 2
    ORDER BY id ASC
    LIMIT 1;
END$$

CREATE PROCEDURE `sesta_site_ayarlari_kaydet` (IN `p_site_url` VARCHAR(255), IN `p_site_baslik` VARCHAR(255), IN `p_site_desc` TEXT, IN `p_marka_adi` VARCHAR(255), IN `p_marka_ust_tanim` VARCHAR(255), IN `p_telefon` VARCHAR(50), IN `p_eposta` VARCHAR(255), IN `p_adres` TEXT, IN `p_harita_linki` TEXT, IN `p_bakim_modu` TINYINT)   BEGIN
    DECLARE v_id INT DEFAULT NULL;

    SELECT id
      INTO v_id
    FROM site_ayarlari
    WHERE sil = 2
    ORDER BY id ASC
    LIMIT 1;

    IF v_id IS NULL THEN
        INSERT INTO site_ayarlari (
            site_url, site_baslik, site_desc, marka_adi, marka_ust_tanim,
            telefon, eposta, adres, harita_linki, bakim_modu, sil
        ) VALUES (
            p_site_url, p_site_baslik, p_site_desc, p_marka_adi, p_marka_ust_tanim,
            p_telefon, p_eposta, p_adres, p_harita_linki, p_bakim_modu, 2
        );
    ELSE
        UPDATE site_ayarlari
        SET site_url = p_site_url,
            site_baslik = p_site_baslik,
            site_desc = p_site_desc,
            marka_adi = p_marka_adi,
            marka_ust_tanim = p_marka_ust_tanim,
            telefon = p_telefon,
            eposta = p_eposta,
            adres = p_adres,
            harita_linki = p_harita_linki,
            bakim_modu = p_bakim_modu
        WHERE id = v_id;
    END IF;
END$$

CREATE PROCEDURE `sesta_talepler_hepsi` ()   BEGIN
    SELECT *
    FROM iletisim_talepleri
    WHERE sil = 2
    ORDER BY talep_id DESC;
END$$

CREATE PROCEDURE `sesta_talepler_sayfa` (IN `p_page` INT, IN `p_limit` INT)   BEGIN
    DECLARE v_page INT DEFAULT IFNULL(p_page, 1);
    DECLARE v_limit INT DEFAULT IFNULL(p_limit, 25);
    DECLARE v_offset INT DEFAULT 0;

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_limit < 1 THEN
        SET v_limit = 25;
    END IF;

    IF v_limit > 100 THEN
        SET v_limit = 100;
    END IF;

    SET v_offset = (v_page - 1) * v_limit;

    SELECT COUNT(*) AS total
    FROM iletisim_talepleri
    WHERE sil = 2;

    SELECT *
    FROM iletisim_talepleri
    WHERE sil = 2
    ORDER BY talep_id DESC
    LIMIT v_limit OFFSET v_offset;
END$$

CREATE PROCEDURE `sesta_talep_ekle` (IN `p_ad_soyad` VARCHAR(255), IN `p_telefon` VARCHAR(50), IN `p_eposta` VARCHAR(255), IN `p_proje_tipi` VARCHAR(255), IN `p_hizmet_alani` VARCHAR(255), IN `p_lokasyon` VARCHAR(255), IN `p_mesaj` TEXT, IN `p_ip_adresi` VARCHAR(64), IN `p_user_agent` TEXT)   BEGIN
    INSERT INTO iletisim_talepleri (
        ad_soyad, telefon, eposta, proje_tipi, hizmet_alani, lokasyon, mesaj, ip_adresi, user_agent
    ) VALUES (
        p_ad_soyad, p_telefon, p_eposta, p_proje_tipi, p_hizmet_alani, p_lokasyon, p_mesaj, p_ip_adresi, p_user_agent
    );

    SELECT LAST_INSERT_ID() AS talep_id;
END$$

CREATE PROCEDURE `sesta_talep_getir` (IN `p_talep_id` INT)   BEGIN
    SELECT *
    FROM iletisim_talepleri
    WHERE talep_id = p_talep_id
      AND sil = 2
    LIMIT 1;
END$$

CREATE PROCEDURE `sesta_talep_guncelle` (IN `p_talep_id` INT, IN `p_okundu` TINYINT, IN `p_admin_notu` TEXT)   BEGIN
    UPDATE iletisim_talepleri
    SET okundu = p_okundu,
        admin_notu = p_admin_notu
    WHERE talep_id = p_talep_id
      AND sil = 2;
END$$

CREATE PROCEDURE `sesta_talep_sil` (IN `p_talep_id` INT)   BEGIN
    UPDATE iletisim_talepleri
    SET sil = 1
    WHERE talep_id = p_talep_id;
END$$

--
-- Functions
--
CREATE FUNCTION `sesta_fn_blog_yayin_durumu` (`p_yazi_id` INT) RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci READS SQL DATA BEGIN
    DECLARE v_result VARCHAR(20) DEFAULT 'Taslak';

    SELECT CASE
        WHEN sil = 1 THEN 'Silinmis'
        WHEN durum <> 1 THEN 'Taslak'
        WHEN yayin_tarihi IS NOT NULL AND yayin_tarihi > CURDATE() THEN 'Planlandi'
        ELSE 'Yayinda'
    END
      INTO v_result
    FROM blog_yazilari
    WHERE yazi_id = p_yazi_id
    LIMIT 1;

    RETURN COALESCE(v_result, 'Taslak');
END$$

CREATE FUNCTION `sesta_fn_hizmet_teslim_sayisi` (`p_hizmet_id` INT) RETURNS INT(11) READS SQL DATA BEGIN
    DECLARE v_count INT DEFAULT 0;

    SELECT COUNT(*)
      INTO v_count
    FROM hizmet_teslimleri
    WHERE hizmet_id = p_hizmet_id
      AND sil = 2
      AND durum = 1;

    RETURN v_count;
END$$

CREATE FUNCTION `sesta_fn_medya_kategori_adedi` (`p_kategori` VARCHAR(100)) RETURNS INT(11) READS SQL DATA BEGIN
    DECLARE v_count INT DEFAULT 0;

    SELECT COUNT(*)
      INTO v_count
    FROM medya
    WHERE sil = 2
      AND (p_kategori IS NULL OR p_kategori = '' OR kategori = p_kategori);

    RETURN v_count;
END$$

CREATE FUNCTION `sesta_fn_proje_gorsel_tam_mi` (`p_proje_id` INT) RETURNS TINYINT(4) READS SQL DATA BEGIN
    DECLARE v_result TINYINT DEFAULT 0;

    SELECT CASE
        WHEN COALESCE(NULLIF(tasarim_gorseli, ''), '') <> ''
         AND COALESCE(NULLIF(final_gorseli, ''), '') <> '' THEN 1
        ELSE 0
    END
      INTO v_result
    FROM projeler
    WHERE proje_id = p_proje_id
    LIMIT 1;

    RETURN COALESCE(v_result, 0);
END$$

CREATE FUNCTION `sesta_fn_slugify` (`p_value` VARCHAR(255)) RETURNS VARCHAR(255) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC BEGIN
    DECLARE v_value VARCHAR(255) DEFAULT '';

    SET v_value = LOWER(TRIM(IFNULL(p_value, '')));
    SET v_value = REPLACE(v_value, 'ı', 'i');
    SET v_value = REPLACE(v_value, 'i̇', 'i');
    SET v_value = REPLACE(v_value, 'ğ', 'g');
    SET v_value = REPLACE(v_value, 'ü', 'u');
    SET v_value = REPLACE(v_value, 'ş', 's');
    SET v_value = REPLACE(v_value, 'ö', 'o');
    SET v_value = REPLACE(v_value, 'ç', 'c');
    SET v_value = REPLACE(v_value, '&', ' ve ');
    SET v_value = REPLACE(v_value, '/', '-');
    SET v_value = REPLACE(v_value, '\\', '-');
    SET v_value = REPLACE(v_value, ' ', '-');
    SET v_value = REPLACE(v_value, '.', '-');
    SET v_value = REPLACE(v_value, ',', '-');
    SET v_value = REPLACE(v_value, ':', '-');
    SET v_value = REPLACE(v_value, ';', '-');
    SET v_value = REPLACE(v_value, '!', '-');
    SET v_value = REPLACE(v_value, '?', '-');
    SET v_value = REPLACE(v_value, '''', '');
    SET v_value = REPLACE(v_value, '"', '');
    SET v_value = REPLACE(v_value, '(', '-');
    SET v_value = REPLACE(v_value, ')', '-');
    SET v_value = REPLACE(v_value, '[', '-');
    SET v_value = REPLACE(v_value, ']', '-');
    SET v_value = REPLACE(v_value, '{', '-');
    SET v_value = REPLACE(v_value, '}', '-');
    SET v_value = REPLACE(v_value, '+', '-');
    SET v_value = REPLACE(v_value, '#', '-');
    SET v_value = REPLACE(v_value, '%', '-');
    SET v_value = REPLACE(v_value, '*', '-');
    SET v_value = REPLACE(v_value, '=', '-');
    SET v_value = REPLACE(v_value, '@', '-');

    WHILE INSTR(v_value, '--') > 0 DO
        SET v_value = REPLACE(v_value, '--', '-');
    END WHILE;

    RETURN TRIM(BOTH '-' FROM v_value);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_kullanicilar`
--

CREATE TABLE `admin_kullanicilar` (
  `kullanici_id` int(11) NOT NULL,
  `ad_soyad` varchar(255) NOT NULL,
  `eposta` varchar(255) NOT NULL,
  `sifre_hash` varchar(255) NOT NULL,
  `failed_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `lock_until` datetime DEFAULT NULL,
  `son_giris_tarihi` datetime DEFAULT NULL,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `mfa_secret_encrypted` text DEFAULT NULL,
  `rutbe` tinyint(4) DEFAULT 1,
  `sil` tinyint(4) DEFAULT 2,
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_kullanicilar`
--


-- --------------------------------------------------------

--
-- Table structure for table `blog_yazilari`
--

CREATE TABLE `blog_yazilari` (
  `yazi_id` int(11) NOT NULL,
  `baslik` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `kapak_gorseli` varchar(255) DEFAULT NULL,
  `yazar` varchar(255) DEFAULT NULL,
  `yayin_tarihi` date DEFAULT NULL,
  `etiketler` text DEFAULT NULL,
  `icerik` longtext DEFAULT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_desc` text DEFAULT NULL,
  `durum` tinyint(4) DEFAULT 2,
  `sil` tinyint(4) DEFAULT 2,
  `ekleyen` int(11) DEFAULT NULL,
  `ekleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blog_yazilari`
--


--
-- Triggers `blog_yazilari`
--
DELIMITER $$
CREATE TRIGGER `sesta_bi_blog_slug_defaults` BEFORE INSERT ON `blog_yazilari` FOR EACH ROW BEGIN
    IF COALESCE(NULLIF(TRIM(NEW.slug), ''), '') = '' THEN
        SET NEW.slug = sesta_fn_slugify(NEW.baslik);
    ELSE
        SET NEW.slug = sesta_fn_slugify(NEW.slug);
    END IF;

    IF NEW.durum IS NULL THEN
        SET NEW.durum = 2;
    END IF;

    IF NEW.sil IS NULL THEN
        SET NEW.sil = 2;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `sesta_bu_blog_slug_timestamp` BEFORE UPDATE ON `blog_yazilari` FOR EACH ROW BEGIN
    IF COALESCE(NULLIF(TRIM(NEW.slug), ''), '') = '' THEN
        SET NEW.slug = sesta_fn_slugify(NEW.baslik);
    ELSE
        SET NEW.slug = sesta_fn_slugify(NEW.slug);
    END IF;

    SET NEW.guncelleme_tarihi = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `faq`
--

CREATE TABLE `faq` (
  `faq_id` int(11) NOT NULL,
  `sirasi` int(11) DEFAULT 0,
  `soru` varchar(255) NOT NULL,
  `cevap` text NOT NULL,
  `durum` tinyint(4) DEFAULT 1,
  `sil` tinyint(4) DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faq`
--


-- --------------------------------------------------------

--
-- Table structure for table `hero_slaytlar`
--

CREATE TABLE `hero_slaytlar` (
  `hero_id` int(11) NOT NULL,
  `sirasi` int(11) DEFAULT 0,
  `eyebrow` varchar(255) DEFAULT NULL,
  `baslik` varchar(255) NOT NULL,
  `alt_baslik` varchar(255) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `gorsel` varchar(255) DEFAULT NULL,
  `cta_bir_metin` varchar(100) DEFAULT NULL,
  `cta_bir_link` varchar(255) DEFAULT NULL,
  `cta_iki_metin` varchar(100) DEFAULT NULL,
  `cta_iki_link` varchar(255) DEFAULT NULL,
  `durum` tinyint(4) DEFAULT 1,
  `sil` tinyint(4) DEFAULT 2,
  `ekleyen` int(11) DEFAULT NULL,
  `ekleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hero_slaytlar`
--


-- --------------------------------------------------------

--
-- Table structure for table `hizmetler`
--

CREATE TABLE `hizmetler` (
  `hizmet_id` int(11) NOT NULL,
  `sirasi` int(11) DEFAULT 0,
  `baslik` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `kisa_ozet` text DEFAULT NULL,
  `detay` text DEFAULT NULL,
  `gorsel` varchar(255) DEFAULT NULL,
  `ne_yapiyoruz` text DEFAULT NULL,
  `hangi_ciktilar` text DEFAULT NULL,
  `kimler_icin` text DEFAULT NULL,
  `surece_katkisi` text DEFAULT NULL,
  `durum` tinyint(4) DEFAULT 1,
  `sil` tinyint(4) DEFAULT 2,
  `ekleyen` int(11) DEFAULT NULL,
  `ekleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hizmetler`
--


--
-- Triggers `hizmetler`
--
DELIMITER $$
CREATE TRIGGER `sesta_bi_hizmetler_slug_defaults` BEFORE INSERT ON `hizmetler` FOR EACH ROW BEGIN
    IF COALESCE(NULLIF(TRIM(NEW.slug), ''), '') = '' THEN
        SET NEW.slug = sesta_fn_slugify(NEW.baslik);
    ELSE
        SET NEW.slug = sesta_fn_slugify(NEW.slug);
    END IF;

    IF NEW.durum IS NULL THEN
        SET NEW.durum = 1;
    END IF;

    IF NEW.sil IS NULL THEN
        SET NEW.sil = 2;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `sesta_bu_hizmetler_slug_timestamp` BEFORE UPDATE ON `hizmetler` FOR EACH ROW BEGIN
    IF COALESCE(NULLIF(TRIM(NEW.slug), ''), '') = '' THEN
        SET NEW.slug = sesta_fn_slugify(NEW.baslik);
    ELSE
        SET NEW.slug = sesta_fn_slugify(NEW.slug);
    END IF;

    SET NEW.guncelleme_tarihi = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `hizmet_teslimleri`
--

CREATE TABLE `hizmet_teslimleri` (
  `teslim_id` int(11) NOT NULL,
  `hizmet_id` int(11) NOT NULL,
  `sirasi` int(11) DEFAULT 0,
  `baslik` varchar(255) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `durum` tinyint(4) DEFAULT 1,
  `sil` tinyint(4) DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hizmet_teslimleri`
--


-- --------------------------------------------------------

--
-- Table structure for table `iletisim_talepleri`
--

CREATE TABLE `iletisim_talepleri` (
  `talep_id` int(11) NOT NULL,
  `ad_soyad` varchar(255) DEFAULT NULL,
  `telefon` varchar(50) DEFAULT NULL,
  `eposta` varchar(255) DEFAULT NULL,
  `proje_tipi` varchar(255) DEFAULT NULL,
  `hizmet_alani` varchar(255) DEFAULT NULL,
  `lokasyon` varchar(255) DEFAULT NULL,
  `mesaj` text DEFAULT NULL,
  `ip_adresi` varchar(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `okundu` tinyint(4) DEFAULT 2,
  `admin_notu` text DEFAULT NULL,
  `sil` tinyint(4) DEFAULT 2,
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `iletisim_talepleri`
--


--
-- Triggers `iletisim_talepleri`
--
DELIMITER $$
CREATE TRIGGER `sesta_bi_talepler_defaults` BEFORE INSERT ON `iletisim_talepleri` FOR EACH ROW BEGIN
    SET NEW.ad_soyad = NULLIF(TRIM(COALESCE(NEW.ad_soyad, '')), '');
    SET NEW.telefon = NULLIF(TRIM(COALESCE(NEW.telefon, '')), '');
    SET NEW.eposta = NULLIF(LOWER(TRIM(COALESCE(NEW.eposta, ''))), '');
    SET NEW.proje_tipi = NULLIF(TRIM(COALESCE(NEW.proje_tipi, '')), '');
    SET NEW.hizmet_alani = NULLIF(TRIM(COALESCE(NEW.hizmet_alani, '')), '');
    SET NEW.lokasyon = NULLIF(TRIM(COALESCE(NEW.lokasyon, '')), '');
    SET NEW.ip_adresi = NULLIF(TRIM(COALESCE(NEW.ip_adresi, '')), '');

    IF NEW.okundu IS NULL THEN
        SET NEW.okundu = 2;
    END IF;

    IF NEW.sil IS NULL THEN
        SET NEW.sil = 2;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `medya`
--

CREATE TABLE `medya` (
  `medya_id` int(11) NOT NULL,
  `dosya_adi` varchar(255) DEFAULT NULL,
  `dosya_yolu` varchar(255) DEFAULT NULL,
  `alt_metin` varchar(255) DEFAULT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `yukleyen` int(11) DEFAULT NULL,
  `durum` tinyint(4) DEFAULT 1,
  `sil` tinyint(4) DEFAULT 2,
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medya`
--


-- --------------------------------------------------------

--
-- Table structure for table `projeler`
--

CREATE TABLE `projeler` (
  `proje_id` int(11) NOT NULL,
  `sirasi` int(11) DEFAULT 0,
  `proje_adi` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `sehir` varchar(100) DEFAULT NULL,
  `yapi_tipi` varchar(255) DEFAULT NULL,
  `kapsam` text DEFAULT NULL,
  `rol` text DEFAULT NULL,
  `teknik_cikti` text DEFAULT NULL,
  `tonaj` varchar(100) DEFAULT NULL,
  `metrekare` varchar(100) DEFAULT NULL,
  `teslim_suresi` varchar(100) DEFAULT NULL,
  `kisa_ozet` text DEFAULT NULL,
  `veri_durumu` varchar(100) DEFAULT 'Taslak proje kaydi',
  `veri_notu` text DEFAULT NULL,
  `one_cikan` tinyint(4) DEFAULT 2,
  `tasarim_gorseli` varchar(255) DEFAULT NULL,
  `final_gorseli` varchar(255) DEFAULT NULL,
  `galeri_gorselleri` longtext DEFAULT NULL,
  `durum` tinyint(4) DEFAULT 1,
  `sil` tinyint(4) DEFAULT 2,
  `ekleyen` int(11) DEFAULT NULL,
  `ekleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projeler`
--


--
-- Triggers `projeler`
--
DELIMITER $$
CREATE TRIGGER `sesta_bi_projeler_slug_defaults` BEFORE INSERT ON `projeler` FOR EACH ROW BEGIN
    IF COALESCE(NULLIF(TRIM(NEW.slug), ''), '') = '' THEN
        SET NEW.slug = sesta_fn_slugify(NEW.proje_adi);
    ELSE
        SET NEW.slug = sesta_fn_slugify(NEW.slug);
    END IF;

    IF COALESCE(NULLIF(TRIM(NEW.veri_durumu), ''), '') = '' THEN
        SET NEW.veri_durumu = 'Taslak proje kaydi';
    END IF;

    IF NEW.one_cikan IS NULL THEN
        SET NEW.one_cikan = 2;
    END IF;

    IF NEW.durum IS NULL THEN
        SET NEW.durum = 1;
    END IF;

    IF NEW.sil IS NULL THEN
        SET NEW.sil = 2;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `sesta_bu_projeler_slug_timestamp` BEFORE UPDATE ON `projeler` FOR EACH ROW BEGIN
    IF COALESCE(NULLIF(TRIM(NEW.slug), ''), '') = '' THEN
        SET NEW.slug = sesta_fn_slugify(NEW.proje_adi);
    ELSE
        SET NEW.slug = sesta_fn_slugify(NEW.slug);
    END IF;

    IF COALESCE(NULLIF(TRIM(NEW.veri_durumu), ''), '') = '' THEN
        SET NEW.veri_durumu = 'Taslak proje kaydi';
    END IF;

    SET NEW.guncelleme_tarihi = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `site_ayarlari`
--

CREATE TABLE `site_ayarlari` (
  `id` int(11) NOT NULL,
  `site_url` varchar(255) NOT NULL,
  `site_baslik` varchar(255) NOT NULL,
  `site_desc` text DEFAULT NULL,
  `marka_adi` varchar(255) DEFAULT NULL,
  `marka_ust_tanim` varchar(255) DEFAULT NULL,
  `telefon` varchar(50) DEFAULT NULL,
  `eposta` varchar(255) DEFAULT NULL,
  `adres` text DEFAULT NULL,
  `harita_linki` text DEFAULT NULL,
  `bakim_modu` tinyint(4) DEFAULT 2,
  `sil` tinyint(4) DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_ayarlari`
--


--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_kullanicilar`
--
ALTER TABLE `admin_kullanicilar`
  ADD PRIMARY KEY (`kullanici_id`),
  ADD UNIQUE KEY `eposta` (`eposta`),
  ADD KEY `idx_admin_kullanicilar_lock_until` (`lock_until`),
  ADD KEY `idx_admin_kullanicilar_sil_id` (`sil`,`kullanici_id`),
  ADD KEY `idx_admin_kullanicilar_rutbe_sil` (`rutbe`,`sil`);

--
-- Indexes for table `blog_yazilari`
--
ALTER TABLE `blog_yazilari`
  ADD PRIMARY KEY (`yazi_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_blog_sil_durum_yayin_id` (`sil`,`durum`,`yayin_tarihi`,`yazi_id`),
  ADD KEY `idx_blog_sil_yayin_id` (`sil`,`yayin_tarihi`,`yazi_id`);

--
-- Indexes for table `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`faq_id`),
  ADD KEY `idx_faq_sil_durum_sirasi_id` (`sil`,`durum`,`sirasi`,`faq_id`),
  ADD KEY `idx_faq_sil_sirasi_id` (`sil`,`sirasi`,`faq_id`);

--
-- Indexes for table `hero_slaytlar`
--
ALTER TABLE `hero_slaytlar`
  ADD PRIMARY KEY (`hero_id`),
  ADD KEY `idx_hero_sil_durum_sirasi_id` (`sil`,`durum`,`sirasi`,`hero_id`),
  ADD KEY `idx_hero_sil_sirasi_id` (`sil`,`sirasi`,`hero_id`);

--
-- Indexes for table `hizmetler`
--
ALTER TABLE `hizmetler`
  ADD PRIMARY KEY (`hizmet_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_hizmetler_sil_durum_sirasi_id` (`sil`,`durum`,`sirasi`,`hizmet_id`),
  ADD KEY `idx_hizmetler_sil_sirasi_id` (`sil`,`sirasi`,`hizmet_id`);

--
-- Indexes for table `hizmet_teslimleri`
--
ALTER TABLE `hizmet_teslimleri`
  ADD PRIMARY KEY (`teslim_id`),
  ADD KEY `idx_teslim_hizmet_sil_durum_sirasi_id` (`hizmet_id`,`sil`,`durum`,`sirasi`,`teslim_id`),
  ADD KEY `idx_teslim_hizmet_sil_sirasi_id` (`hizmet_id`,`sil`,`sirasi`,`teslim_id`);

--
-- Indexes for table `iletisim_talepleri`
--
ALTER TABLE `iletisim_talepleri`
  ADD PRIMARY KEY (`talep_id`),
  ADD KEY `idx_talep_sil_id` (`sil`,`talep_id`),
  ADD KEY `idx_talep_sil_okundu_tarih` (`sil`,`okundu`,`kayit_tarihi`);

--
-- Indexes for table `medya`
--
ALTER TABLE `medya`
  ADD PRIMARY KEY (`medya_id`),
  ADD KEY `idx_medya_sil_durum_id` (`sil`,`durum`,`medya_id`),
  ADD KEY `idx_medya_sil_kategori_dosya_id` (`sil`,`kategori`,`dosya_adi`,`medya_id`),
  ADD KEY `idx_medya_sil_kategori` (`sil`,`kategori`);

--
-- Indexes for table `projeler`
--
ALTER TABLE `projeler`
  ADD PRIMARY KEY (`proje_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_projeler_sil_durum_sirasi_id` (`sil`,`durum`,`sirasi`,`proje_id`),
  ADD KEY `idx_projeler_sil_sirasi_id` (`sil`,`sirasi`,`proje_id`),
  ADD KEY `idx_projeler_sil_durum_one_sirasi_id` (`sil`,`durum`,`one_cikan`,`sirasi`,`proje_id`);

--
-- Indexes for table `site_ayarlari`
--
ALTER TABLE `site_ayarlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site_ayarlari_sil_id` (`sil`,`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_kullanicilar`
--
ALTER TABLE `admin_kullanicilar`
  MODIFY `kullanici_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `blog_yazilari`
--
ALTER TABLE `blog_yazilari`
  MODIFY `yazi_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `faq`
--
ALTER TABLE `faq`
  MODIFY `faq_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `hero_slaytlar`
--
ALTER TABLE `hero_slaytlar`
  MODIFY `hero_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hizmetler`
--
ALTER TABLE `hizmetler`
  MODIFY `hizmet_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hizmet_teslimleri`
--
ALTER TABLE `hizmet_teslimleri`
  MODIFY `teslim_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `iletisim_talepleri`
--
ALTER TABLE `iletisim_talepleri`
  MODIFY `talep_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `medya`
--
ALTER TABLE `medya`
  MODIFY `medya_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `projeler`
--
ALTER TABLE `projeler`
  MODIFY `proje_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `site_ayarlari`
--
ALTER TABLE `site_ayarlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
