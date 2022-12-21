<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Laravie\Parser\Xml\Reader;
use Laravie\Parser\Xml\Document;

use App\Models\Laboratory;
use App\Models\Measurement;
use App\Models\Result;
use App\Models\Sample;


class DirectoryParser
{
    public function parseDir()
    {
        $srcFiles = Storage::disk('lims')->files();
        //dd($srcFiles);
        foreach($srcFiles as $file) {
            dd($file);

            $objJsonDocument = json_encode($xml);
            $arrOutput = json_decode($objJsonDocument, TRUE);

            foreach($arrOutput['sample'] as $xmlSample)
            {
                // the sample number is the key between xml source file and database destination table
                $sampleNumber = $xmlSample['number'];
                $laboratory = Laboratory::where('code', '=', $xmlSample['measurement']['laboratory'])->first();

                $sample = Sample::where('number', '=', $sampleNumber)->first();
                //$sample = Sample::where('number', '=', '22-01502')->first();

                if($sample) {
                    // update the mtime
                    $sample->mtime = $xmlSample['@attributes']['mtime'];
                    $sample->save();

                    // delete linked measurements and results
                    $measurements = Measurement::where('sample_id', '=', $sample->id)->get();
                    foreach($measurements as $measurement) {
                        Result::where('measurement_id', '=', $measurement->id)->delete();
                        $measurement->delete();
                    }
                } else {
                    //insert the new sample
                    $sample = new Sample();
                    $sample->number = $sampleNumber;
                    $sample->bag_code_id = $sample['bag-code'];
                    $sample->laboratory_id = 77;

                    $sample->save();
                }

                // sample is now up to date, insert measurement and results
                $xmlMeasurement = $xmlSample['measurement'];
                //dd($xmlMeasurement);
                $xmlResults = $xmlMeasurement['results'];

                /*$measurement = new Measurement();
                $measurement->sample_id = $sample->id;
                $measurement->save();*/

                //$test = $this->array_keys_multi($xmlResults);
                if(is_array($xmlResults['result'])) {
                    dd($xmlResults['result']);
                }
                foreach($xmlResults['result'] as $xmlResult) {
                    dd($xmlResult['nuclide']);
                    /*$result = new Result();
                    $result->measurement_id = $measurement->id;
                    $result->nuclide = '';
                    $result->value = '';
                    $result->error = '';
                    $result->save();*/
                }


            }

            // when all data processed, move the file to "processed" folder
            // Storage::disk('lims')->move($file, 'processed/'. $file);
        }
    }

    public function array_keys_multi(array $array)
    {
        $keys = array();
        foreach ($array as $key => $value) {
            $keys[] = $key;
            if (is_array($array[$key])) {
                $keys = array_merge($keys, $this->array_keys_multi($array[$key]));
            }
        }
        return $keys;
    }
}
