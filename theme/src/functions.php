<?php

class Functions
{
    public function get_cover_media_html($postdata, $css_class = '')
    {
        $op = '';
        if (is_array($postdata['cover_media'])) {
            if (count($postdata['cover_media']) == 1) {
                $op .= '<img src="' . $postdata['cover_media'][0] . '" class="w-100 ' . $css_class . '">';
            } else {
                $op .= '<div class="w-100 ' . $css_class . '" data-flickity=\'{"imagesLoaded": true, "percentPosition": false, "adaptiveHeight": true, "setGallerySize": true}\'>';
                foreach ($postdata['cover_media'] as $cover_media) {
                    if ($cover_media) {
                        $op .= '<img src="' . $cover_media . '">';
                    }

                }
                $op .= '</div>';
            }
        } else {
            $op .= '<img src="' . $postdata['cover_media'] . '" class="w-100 ' . $css_class . '">';
        }

        return $op;
    }

    public static function gst_states()
    {
        $gst_state = array();
        $gst_state['35'] = 'Andaman and Nicobar Islands';
        $gst_state['37'] = 'Andhra Pradesh';
        $gst_state['12'] = 'Arunachal Pradesh';
        $gst_state['18'] = 'Assam';
        $gst_state['10'] = 'Bihar';
        $gst_state['4'] = 'Chandigarh';
        $gst_state['22'] = 'Chattisgarh';
        $gst_state['26'] = 'Dadra & Nagar Haveli and Daman & Diu';
        $gst_state['7'] = 'Delhi';
        $gst_state['30'] = 'Goa';
        $gst_state['24'] = 'Gujarat';
        $gst_state['6'] = 'Haryana';
        $gst_state['2'] = 'Himachal Pradesh';
        $gst_state['1'] = 'Jammu and Kashmir';
        $gst_state['20'] = 'Jharkhand';
        $gst_state['29'] = 'Karnataka';
        $gst_state['32'] = 'Kerala';
        $gst_state['38'] = 'Ladakh';
        $gst_state['31'] = 'Lakshadweep Islands';
        $gst_state['23'] = 'Madhya Pradesh';
        $gst_state['27'] = 'Maharashtra';
        $gst_state['14'] = 'Manipur';
        $gst_state['17'] = 'Meghalaya';
        $gst_state['15'] = 'Mizoram';
        $gst_state['13'] = 'Nagaland';
        $gst_state['21'] = 'Odisha';
        $gst_state['97'] = 'Other Territory';
        $gst_state['34'] = 'Pondicherry';
        $gst_state['3'] = 'Punjab';
        $gst_state['8'] = 'Rajasthan';
        $gst_state['11'] = 'Sikkim';
        $gst_state['33'] = 'Tamil Nadu';
        $gst_state['36'] = 'Telangana';
        $gst_state['16'] = 'Tripura';
        $gst_state['9'] = 'Uttar Pradesh';
        $gst_state['5'] = 'Uttarakhand';
        $gst_state['19'] = 'West Bengal';
        return $gst_state;
    }

    public function csv_to_array($url)
    {
        $path = str_replace(BASE_URL, ABSOLUTE_PATH, urldecode($url));

        $csv = array_map('str_getcsv', file($path));
        $headers = $csv[0];
        unset($csv[0]);
        $rowsWithKeys = [];
        foreach ($csv as $row) {
            $newRow = [];
            foreach ($headers as $k => $key) {
                $newRow[$key] = $row[$k];
            }
            $rowsWithKeys[] = $newRow;
        }
        return $rowsWithKeys;
    }

}