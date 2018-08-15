<?php

namespace App\Exports;

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UsersExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;
    private $settings;

    public function __construct($validate)
    {
        $this->settings = $validate;
    }

    public function query()
    {
        //init Query Builder
        switch ($this->settings['state']) {
            case 'withTrashed':
                $query = User::query()->withTrashed();
                break;
            case 'onlyTrashed':
                $query = User::query()->onlyTrashed();
                break;
            default:
                $query = User::query();
        }
        //Check gifts relationship
        if (!is_null($this->settings['gift'])) {
            switch ($this->settings['gift']) {
                case 0:
                    $query = $query->has('gifts');
                    break;
                case 1:
                    $query = $query->doesntHave('gifts');
                    break;
                default;
            }
        }

        /*todo
        *Mettre softdelete sur subs plus facile avec request sur subs
         * Que les comptes actifs sont pris dans le requete
         * sinon trouvé un moyen de check que latest
         *
         */

        //Type of last membership
        if (!is_null($this->settings['type'])) {
            $type = $this->settings['type'];
            $query = $query->whereHas('subscriptions', function ($sub) use ($type) {
                $sub->where('subscription_type_id', '=', $type)
                    ->where('date_end', '>', Carbon::now()->toDateString());
            });
        };
        //Start and End date
        if (!is_null($this->settings['startDate'])) {
            $date = $this->settings['startDate'];
            $query = $query->whereHas('subscriptions', function ($sub) use ($date) {
                $sub->where('date_end', '>=', $date);
            });
        };

        if (!is_null($this->settings['endDate'])) {
            $date = $this->settings['endDate'];
            $query = $query->whereHas('subscriptions', function ($sub) use ($date) {
                $sub->where('date_start', '<=', $date);
            });
        };

        //Where Clause array
        $queries = array();

        //Regex cellphone and phone
        if (!is_null($this->settings['phone'])) {
            $query = $query->where('phone_1', 'REGEXP', $this->settings['phone']);
            $query = $query->orWhere('phone_2', 'REGEXP', $this->settings['phone']);
        }

        //Age query
        if (!is_null($this->settings['ageNumber'])) {
            array_push($queries, ['birthdate', $this->settings['ageOperator'], Carbon::now()
                ->subYear($this->settings['ageNumber'])]);
        }
        //unset bad $key in array settings for foreach
        unset($this->settings['exportFile'], $this->settings['exportFormat'], $this->settings['state'],
            $this->settings['type'], $this->settings['startDate'], $this->settings['endDate'],
            $this->settings['ageOperator'], $this->settings['ageNumber'], $this->settings['phone'],
            $this->settings['gift']);

        //Build last queries
        foreach ($this->settings as $key => $value) {
            if (!is_null($value)) {

                array_push($queries, [$key, '=', $value]);
            }
        }

        //Add where clause in query
        if (!empty($queries)) {

            return $query = $query->where($queries);

        } else {
            return $query;
        }
    }

//Set labels first row of table
    public
    function headings(): array
    {
        return [
            '#',
            'Genre',
            'Nom',
            'Prenom',
            'Date de naissance',
            'Adresse 1',
            'Adresse 2',
            'Code postal',
            'Ville',
            'Email',
            'Genre conjoint',
            'Prenom conjoint',
            'Nom conjoint',
            'Naissance conjoint',
            'Email conjoint',
            'Telephone 1',
            'Telephone 2',
            'Bénévole',
            'Détails bénévolat',
            'Distributeur',
            'Journal',
            'Newsletter',
            'Mailing',
            'Commentaire',
            'Alerte',
            'Date de création',
            'Date de mise à jour',
            'Date de suppression',
        ];
    }

//Map value 0/1 to Female/Male
    public
    function map($users): array
    {
        $users = $users->toArray();
        foreach ($users as $key => $value) {
            if (is_int($value) && ($key == 'gender' || $key == 'gender_joint')) {
                if ($value === 1) {
                    $users[$key] = 'Masculin';
                } elseif ($value === 2) {
                    $users[$key] = 'Feminin';
                } else {
                    $users[$key] = 'Indéfini';
                }
            }
        }

        return $users;
    }
}
