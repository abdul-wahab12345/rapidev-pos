<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

/**
 * Pakistan cities for address pickers — grouped by ICT + provinces; GB/AJK retain district centres from prior seed.
 *
 * Punjab–Balochistan block matches the project's reference city list (with spelling / casing normalization).
 */
class PakistanCitiesSeeder extends Seeder
{
    public function run(): void
    {
        if (City::query()->exists()) {
            return;
        }

        $batch = [];
        $order = 0;
        $now = now();

        foreach ($this->districtsByProvince() as $province => $names) {
            $names = array_values(array_unique($names));

            foreach ($names as $name) {
                if ($name === '') {
                    continue;
                }
                $batch[] = [
                    'name' => $name,
                    'province' => $province,
                    'sort_order' => $order++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($batch, 100) as $chunk) {
            City::insert($chunk);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    protected function districtsByProvince(): array
    {
        return [
            'Islamabad Capital' => [
                'Islamabad',
            ],

            /** @see Punjab city block (Islamabad omitted — ICT row above). */
            'Punjab' => [
                'Ahmed Nager',
                'Ahmadpur East',
                'Ali Khan',
                'Alipur',
                'Arifwala',
                'Attock',
                'Bhera',
                'Bhalwal',
                'Bahawalnagar',
                'Bahawalpur',
                'Bhakkar',
                'Burewala',
                'Chillianwala',
                'Chakwal',
                'Chichawatni',
                'Chiniot',
                'Chishtian',
                'Daska',
                'Darya Khan',
                'Dera Ghazi Khan',
                'Dhaular',
                'Dina',
                'Dinga',
                'Dipalpur',
                'Faisalabad',
                'Fateh Jhang',
                'Ghakhar Mandi',
                'Gojra',
                'Gujranwala',
                'Gujrat',
                'Gujar Khan',
                'Hafizabad',
                'Haroonabad',
                'Hasilpur',
                'Haveli',
                'Lakha',
                'Jalalpur',
                'Jattan',
                'Jampur',
                'Jaranwala',
                'Jhang',
                'Jhelum',
                'Kalabagh',
                'Karor Lal Esan',
                'Kasur',
                'Kamalia',
                'Kamoke',
                'Khanewal',
                'Khanpur',
                'Kharian',
                'Khushab',
                'Kot Adu',
                'Jauharabad',
                'Lahore',
                'Lalamusa',
                'Layyah',
                'Liaquat Pur',
                'Lodhran',
                'Malakwal',
                'Mamoori',
                'Mailsi',
                'Mandi Bahauddin',
                'Mian Channu',
                'Mianwali',
                'Multan',
                'Murree',
                'Muridke',
                'Mianwali Bangla',
                'Muzaffargarh',
                'Narowal',
                'Okara',
                'Renala Khurd',
                'Pakpattan',
                'Pattoki',
                'Pir Mahal',
                'Qaimpur',
                'Qila Didar',
                'Rabwah',
                'Raiwind',
                'Rajanpur',
                'Rahim Yar Khan',
                'Rawalpindi',
                'Sadiqabad',
                'Safdarabad',
                'Sahiwal',
                'Sangla Hill',
                'Sarai Alamgir',
                'Sargodha',
                'Shakargarh',
                'Sheikhupura',
                'Sialkot',
                'Sohawa',
                'Soianwala',
                'Siranwali',
                'Talagang',
                'Taxila',
                'Toba Tek Singh',
                'Vehari',
                'Wah Cantonment',
                'Wazirabad',
            ],

            /** @see Sindh city block. */
            'Sindh' => [
                'Badin',
                'Bhirkan',
                'Rajo Khanani',
                'Chak',
                'Dadu',
                'Digri',
                'Diplo',
                'Dokri',
                'Ghotki',
                'Haala',
                'Hyderabad',
                'Islamkot',
                'Jacobabad',
                'Jamshoro',
                'Jungshahi',
                'Kandhkot',
                'Kandiaro',
                'Karachi',
                'Kashmore',
                'Keti Bandar',
                'Khairpur',
                'Kotri',
                'Larkana',
                'Matiari',
                'Mehar',
                'Mirpur Khas',
                'Mithani',
                'Mithi',
                'Mehrabpur',
                'Moro',
                'Nagarparkar',
                'Naudero',
                'Naushahro Feroze',
                'Naushara',
                'Nawabshah',
                'Nazimabad',
                'Qambar',
                'Qasimabad',
                'Ranipur',
                'Ratodero',
                'Rohri',
                'Sakrand',
                'Sanghar',
                'Shahbandar',
                'Shahdadkot',
                'Shahdadpur',
                'Shahpur Chakar',
                'Shikarpur',
                'Sukkur',
                'Tangwani',
                'Tando Adam',
                'Tando Allahyar',
                'Tando Muhammad Khan',
                'Thatta',
                'Umerkot',
                'Warah',
            ],

            /** @see Khyber Pakhtunkhwa city block ("Darya Khan" also exists here — distinct from Punjab row by province). */
            'Khyber Pakhtunkhwa' => [
                'Abbottabad',
                'Adezai',
                'Alpuri',
                'Akora Khattak',
                'Ayubia',
                'Banda Daud',
                'Bannu',
                'Batkhela',
                'Battagram',
                'Birote',
                'Chakdara',
                'Charsadda',
                'Chitral',
                'Daggar',
                'Dargai',
                'Darya Khan',
                'Dera Ismail Khan',
                'Doaba',
                'Dir',
                'Drosh',
                'Hangu',
                'Haripur',
                'Karak',
                'Kohat',
                'Kulachi',
                'Lakki Marwat',
                'Latamber',
                'Madyan',
                'Mansehra',
                'Mardan',
                'Mastuj',
                'Mingora',
                'Nowshera',
                'Paharpur',
                'Pabbi',
                'Peshawar',
                'Saidu Sharif',
                'Shorkot',
                'Shewa Adda',
                'Swabi',
                'Swat',
                'Tangi',
                'Tank',
                'Thall',
                'Timergara',
                'Tordher',
            ],

            /** @see Balochistan city block. */
            'Balochistan' => [
                'Awaran',
                'Barkhan',
                'Chagai',
                'Dera Bugti',
                'Gwadar',
                'Harnai',
                'Jafarabad',
                'Jhal Magsi',
                'Kacchi',
                'Kalat',
                'Kech',
                'Kharan',
                'Khuzdar',
                'Killa Abdullah',
                'Killa Saifullah',
                'Kohlu',
                'Lasbela',
                'Lehri',
                'Loralai',
                'Mastung',
                'Musakhel',
                'Nasirabad',
                'Nushki',
                'Panjgur',
                'Pishin Valley',
                'Quetta',
                'Sherani',
                'Sibi',
                'Sohbatpur',
                'Washuk',
                'Zhob',
                'Ziarat',
            ],

            'Gilgit Baltistan' => [
                'Astore', 'Ghanche', 'Ghizer', 'Gilgit', 'Hunza', 'Kharmang', 'Nagar', 'Shigar', 'Skardu',
            ],

            'Azad Jammu Kashmir' => [
                'Bagh', 'Bhimber', 'Hattian Bala', 'Haveli', 'Kotli', 'Mirpur', 'Muzaffarabad',
                'Neelum', 'Poonch', 'Sudhnuti',
            ],
        ];
    }
}
