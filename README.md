# Veritabani Odevi - Dolunay Muhendislik

Bu repo, gercek dunya senaryosu olarak secilen bir muhendislik/isletme firmasi icin hazirlanmis web sitesi ve admin panelini icerir.

Canli site: [https://dolunaymuhendislik.com](https://dolunaymuhendislik.com)

Admin panel adresi: [https://dolunaymuhendislik.com/admin/](https://dolunaymuhendislik.com/admin/)

## Odev icin once bakilmasi gereken yerler

1. [database/dolunay_admin.sql](database/dolunay_admin.sql)
   Veritabani dump dosyasidir. Tablolar, iliskiler, stored procedure'ler, function'lar ve trigger'lar bu dosyada yer alir.

2. [admin/sistem/classes/AdminStoredProcedureDal.php](admin/sistem/classes/AdminStoredProcedureDal.php)
   Data Access Layer burada merkezilestirildi. Veritabani cagrilari bu katmanda stored procedure `CALL` uzerinden yapilir.

3. [admin/sistem/classes/AdminCode.php](admin/sistem/classes/AdminCode.php)
   Business Layer gorevindedir. Admin islemleri burada toplanir ve DAL uzerinden procedure cagrilir.

4. [admin/api/](admin/api/)
   Admin ve public API uçlari burada bulunur. Bu dosyalar dogrudan SQL calistirmaz; is mantigi katmanina gider.

5. [admin/pages/](admin/pages/)
   Presentation Layer / admin arayuzu bu klasordedir.

## Odev kosuluna uygunluk notu

- Admin tarafinda dogrudan `SELECT`, `INSERT`, `UPDATE`, `DELETE` kullanilmamistir.
- Veritabani islemleri `DAL -> Stored Procedure` akisina gore duzenlenmistir.
- Admin tarafinda veritabanina dogrudan ulasim yerine procedure cagrilari kullanilmistir.
- Guvenlik nedeniyle repo icindeki ortam degiskenleri ve gizli anahtarlar placeholder olarak birakilmistir.

## Proje senaryosu

Senaryo, yapisal celik imalat, montaj, projelendirme ve teknik koordinasyon hizmeti veren bir isletme uzerinden kurgulanmistir.

Sitede bulunan temel alanlar:

- Hizmetler
- Projeler
- Blog
- Medya
- SSS
- Iletisim talepleri
- Admin kullanici yonetimi
- Site ayarlari

## Canli sistemde neler gorulebilir

- Kurumsal site icerikleri
- Iletisim formu
- Proje ve hizmet listelemeleri
- Admin panel uzerinden icerik yonetimi

## Kisa teknik ozet

- UI katmani: `admin/pages/`
- Business katmani: `admin/sistem/classes/AdminCode.php`
- DAL katmani: `admin/sistem/classes/AdminStoredProcedureDal.php`
- SQL dump: `database/dolunay_admin.sql`

Odevi incelerken once SQL dump dosyasi, sonra DAL ve Business katmani birlikte incelenmelidir.
