<?php

/**
 * Dokümandan gelen sabit bilgiler (Test Ortamı)
 */
define('PARAMPOS_CLIENT_CODE',    '10738');
define('PARAMPOS_USERNAME',       'Test');
define('PARAMPOS_PASSWORD',       'Test');
define('PARAMPOS_GUID',           '0c13d406-873b-403b-9c09-a5766840d98c');
define('PARAMPOS_WSDL_URL',       'https://testposws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx?WSDL');

/**
 * SHA2B64 = (SHA256 sonucun base64 ile kodlanması)
 * Dökümanda: "Islem_Hash = SHA2B64(Islem_Guvenlik_Str)"
 */
function sha2b64($data) {
    return base64_encode(hash('sha256', $data, true));
}

/**
 * 3D Ödeme Başlatma (Pos_Odeme) Fonksiyonu
 * Dokümandaki "Islem_Tutar", "Toplam_Tutar", "Taksit", "Islem_Hash", vb. alanlar
 */
function parampos3DPayment($islem_tutar, $siparis_id, $kart_adsoyad, $kart_no, $sk_ay, $sk_yil, $cvv, $hata_url, $basarili_url, $taksit = "1") 
{
    // 1) Komisyon yoksa => Toplam_Tutar = Islem_Tutar
    // Dökümanda Islem_Tutar "virgüllü" formatta istenir (ör. "100,50")
    // Biz . (nokta) => , (virgül) çeviriyoruz:
    $islem_tutar  = str_replace('.', ',', $islem_tutar);
    $toplam_tutar = $islem_tutar; // eğer komisyon yoksa aynısını kullanın

    // 2) Islem_Guvenlik_Str dokümanda: 
    //    "CLIENT_CODE & GUID & Taksit & Islem_Tutar & Toplam_Tutar & Siparis_ID & Hata_URL & Basarili_URL"
    $islem_guvenlik_str = PARAMPOS_CLIENT_CODE 
                        . PARAMPOS_GUID 
                        . $taksit
                        . $islem_tutar
                        . $toplam_tutar
                        . $siparis_id
                        . $hata_url
                        . $basarili_url;

    // 3) Islem_Hash = SHA2B64(islem_guvenlik_str)
    $islem_hash = sha2b64($islem_guvenlik_str);

    // 4) SOAP Güvenlik Nesnesi (ST_WS_Guvenlik)
    $guvenlik = [
        'CLIENT_CODE'     => PARAMPOS_CLIENT_CODE,
        'CLIENT_USERNAME' => PARAMPOS_USERNAME,
        'CLIENT_PASSWORD' => PARAMPOS_PASSWORD
    ];

    // 5) ST_TP_Islem_Odeme nesnesi (dokümanda "Gönderilecek Parametreler" tablosu):
    $requestParams = [
        'G'                => $guvenlik,
        'GUID'             => PARAMPOS_GUID,
        'KK_Sahibi'        => $kart_adsoyad,
        'KK_No'            => $kart_no,
        'KK_SK_Ay'         => $sk_ay,
        'KK_SK_Yil'        => $sk_yil,
        'KK_CVC'           => $cvv,
        'KK_Sahibi_GSM'    => '5554443322', // Dokümanda zorunlu. (Başında 0 olmadan)
        'Hata_URL'         => $hata_url,
        'Basarili_URL'     => $basarili_url,
        'Siparis_ID'       => $siparis_id,
        'Siparis_Aciklama' => 'Test Ödeme',
        'Taksit'           => $taksit,
        'Islem_Tutar'      => $islem_tutar,      // Virgüllü format ("100,50")
        'Toplam_Tutar'     => $toplam_tutar,     // Virgüllü format
        'Islem_Hash'       => $islem_hash,
        'Islem_Guvenlik_Tip' => '3D',
        'Islem_ID'         => '',
        'IPAdr'            => $_SERVER['REMOTE_ADDR'] ?? '',
        'Ref_URL'          => '', // Opsiyonel
        // Data1...Data10 opsiyonel alanlar
    ];

    // 6) SOAP ile "Pos_Odeme" metodunu çağır
    try {
        $soap_options = ['trace' => 1, 'exceptions' => 1];
        $client = new SoapClient(PARAMPOS_WSDL_URL, $soap_options);
        $response = $client->Pos_Odeme($requestParams);
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "SOAP Hatası: " . $e->getMessage()
        ];
    }

    // 7) Dönen sonuc
    if (!isset($response->Pos_OdemeResult->Sonuc)) {
        return [
            'success' => false,
            'message' => "Geçersiz yanıt"
        ];
    }
    $sonuc     = $response->Pos_OdemeResult->Sonuc;
    $sonuc_str = $response->Pos_OdemeResult->Sonuc_Str ?? '';
    $ucd_url   = $response->Pos_OdemeResult->UCD_URL   ?? '';

    // Sonuc > 0 => 3D yönlendirmesi
    if ($sonuc > 0 && $ucd_url) {
        return [
            'success'   => true,
            'Sonuc'     => $sonuc,
            'Sonuc_Str' => $sonuc_str,
            'UCD_URL'   => $ucd_url
        ];
    } else {
        return [
            'success' => false,
            'Sonuc'   => $sonuc,
            'message' => $sonuc_str
        ];
    }
}

/**
 * Hash Kontrolü (3D dönüş sonrasında)
 * Dokümanda: "Hash Değerini Oluşturan Parametreler"
 *  CLIENT_CODE + GUID + Dekont_ID + Tahsilat_Tutari + Siparis_ID + Islem_ID => SHA1 + Base64
 */
function paramposVerifyHash($dekont_id, $tahsilat_tutari, $siparis_id, $islem_id = '') {
    // Dökümanda: "100,50" gibi virgüllü tutar, DB'ye kaydederken de 
    // noktalı formata dönüştürebilirsiniz. Hash hesaplamasında dokümanda "virgüllü" geçer.
    $kontrol_str = PARAMPOS_CLIENT_CODE . PARAMPOS_GUID . $dekont_id . $tahsilat_tutari . $siparis_id . $islem_id;

    $binary_sha1 = sha1($kontrol_str, true);
    return base64_encode($binary_sha1);
}

// Opsiyonel: BIN / Taksit sorgusu
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_GET['action'] === 'card_info') {
        $bin = substr($_GET['bin'] ?? '', 0, 6);
        // Dokümanda "BIN Sorgulama" metodu varsa orayı çağırabilirsiniz.
        // Örnek sabit cevap:
        echo json_encode([
            'success' => true,
            'data' => [
                'Kart_Tip'   => 'MasterCard',
                'Kart_Banka' => 'Param Bank'
            ]
        ]);
        exit;
    } 
    elseif ($_GET['action'] === 'installment_info') {
        // Taksit listesi döndürmek için
        echo json_encode([
            'success' => true,
            'data' => [
                ['Taksit' => 1],
                ['Taksit' => 2],
                ['Taksit' => 3]
            ]
        ]);
        exit;
    }
}