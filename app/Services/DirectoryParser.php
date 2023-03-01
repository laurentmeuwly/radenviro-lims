<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Laravie\Parser\Xml\Reader;
use Laravie\Parser\Xml\Document;

use App\Models\BagCode;
use App\Models\Canton;
use App\Models\Country;
use App\Models\Laboratory;
use App\Models\Measurement;
use App\Models\Method;
use App\Models\Network;
use App\Models\Nuclide;
use App\Models\Result;
use App\Models\ResultUnit;
use App\Models\Sample;
use App\Models\Station;
use App\Models\Type;


class DirectoryParser
{
    public function parseDir()
    {
        $srcFiles = Storage::disk('lims')->files();

        foreach($srcFiles as $file) {

            $xml = simplexml_load_file(Storage::disk('lims')->path($file)) or die("Error: Cannot create object");

            $objJsonDocument = json_encode($xml);
            $arrOutput = json_decode($objJsonDocument, true);

            try {
                if(isset($arrOutput['sample'][0])){
                    foreach($arrOutput['sample'] as $xmlSample) {
                        $res = $this->manageSample($xmlSample);
                    }
                } else {
                    $xmlSample = $arrOutput['sample'];
                    $res = $this->manageSample($xmlSample);
                }

                // when all data processed, move the file to "processed" folder
                if($res) {
                    Storage::disk('lims')->move($file, 'processed/'. $file);
                }
            } catch(\Exception $e) {
                report('XML file error: file=' . $file . '; error=' . $e->getMessage());
                Storage::disk('lims')->move($file, 'error/'. $file);
            }
        }
    }


    public function manageSample($xmlSample)
    {
        // the sample number is the key between xml source file and database destination table
        if(!isset($xmlSample['number']) || !isset($xmlSample['@attributes']['mtime'])) {
            throw new \Exception('Not a valid LIMS sample! Missing sample number or mtime');
        }
        $sampleNumber = $xmlSample['number'];

        // mandatory fields
        if( !isset($xmlSample['laboratory'])
            || !isset($xmlSample['data']['bag-code'])
            || !isset($xmlSample['data']['type']) ) {
            throw new \Exception('Mandatory fields missing! Check laboratory, bag-code and type');
        }
        $laboratory = Laboratory::where('code', '=', $xmlSample['laboratory'])->first();
        $bagCode = BagCode::where('version', '=', '2003')->where('code', '=', $xmlSample['data']['bag-code'])->first();
        $type = Type::where('code', '=', $xmlSample['data']['type'])->first();

        $sample = Sample::where('number', '=', $sampleNumber)->first();

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
            $sample->mtime = $xmlSample['@attributes']['mtime'];
            $sample->bag_code_id = $bagCode->id;
            $sample->laboratory_id = $laboratory->id;
            $sample->type_id = $type->id;

            if(isset($xmlSample['data']['station'])) {
                $station = Station::where('code', '=', $xmlSample['data']['station']['@attributes']['name'])->first();
                $network = Network::where('code', '=', $xmlSample['data']['station']['@attributes']['network'])->first();
                $sample->station_id = $station->id;
                $sample->network_id = $network->id;
            }

            if(isset($xmlSample['data']['description'])) {
                $sample->description = $xmlSample['data']['description'];
            }

            if(isset($xmlSample['data']['in-situ'])) {
                $sample->inSitu = $xmlSample['data']['in-situ']==='false' ? 0 : 1;
            }

            if(isset($xmlSample['data']['sampling'])) {

                if(isset($xmlSample['data']['sampling']['location'])) {

                    if(isset($xmlSample['data']['sampling']['location']['coordinates'])) {
                        $sample->samCoordinateSystem = $xmlSample['data']['sampling']['location']['coordinates']['@attributes']['system'];
                        $sample->samCoordinateUnit = $xmlSample['data']['sampling']['location']['coordinates']['@attributes']['unit'];
                        $sample->samX = $xmlSample['data']['sampling']['location']['coordinates']['x'];
                        $sample->samY = $xmlSample['data']['sampling']['location']['coordinates']['y'];
                    }

                    if(isset($xmlSample['data']['sampling']['location']['postcode'])) {
                        $sample->samZip = $xmlSample['data']['sampling']['location']['postcode'];
                    }

                    if(isset($xmlSample['data']['sampling']['location']['town'])) {
                        $sample->samLocality = $xmlSample['data']['sampling']['location']['town'];
                    }

                    if(isset($xmlSample['data']['sampling']['location']['canton'])) {
                        $canton = Canton::where('code', '=', $xmlSample['data']['sampling']['location']['canton'])->first();
                        $sample->samcanton_id = $canton->id;
                    }
                    if(isset($xmlSample['data']['sampling']['location']['country'])) {
                        $country = Country::where('code', '=', $xmlSample['data']['sampling']['location']['country'])->first();
                        $sample->samcountry_id = $country->id;
                    }

                }

                if(isset($xmlSample['data']['sampling']['date'])) {
                    $sample->samDate = $xmlSample['data']['sampling']['date'];
                }

                if(isset($xmlSample['data']['sampling']['end-date'])) {
                    $sample->samEndDate = $xmlSample['data']['sampling']['end-date'];
                }

            }

            if(isset($xmlSample['data']['origin'])) {
                $sample->oriSame = $xmlSample['data']['origin']['@attributes']==='true' ? 1 : 0;
            }

            if(isset($xmlSample['data']['comment'])) {
                $sample->comment = $xmlSample['data']['comment'];
            }

            $sample->save();
        }

        if(isset($xmlSample['measurement'])){
            if(isset($xmlSample['measurement'][0])){
                foreach($xmlSample['measurement'] as $xmlMeasurement) {
                    $res = $this->manageMeasurement($xmlMeasurement, $sample);
                }
            } else {
                $xmlMeasurement = $xmlSample['measurement'];
                $res = $this->manageMeasurement($xmlMeasurement, $sample);
            }
            return $res;
        }

        return true;
    }

    public function manageMeasurement($xmlMeasurement, $sample)
    {
        // measurement without result does not serve
        if(!isset($xmlMeasurement['results'])) {
            throw new \Exception('Measurement without results!');
        }
        $xmlResults = $xmlMeasurement['results'];

        $measurement = new Measurement();

        $measurement->sample_id = $sample->id;

        if(isset($xmlMeasurement['laboratory'])){
            $laboratory = Laboratory::where('code', '=', $xmlMeasurement['laboratory'])->first();
            $measurement->laboratory_id = $laboratory->id;
        }
        if(isset($xmlMeasurement['method'])){
            $method = Method::where('code', '=', $xmlMeasurement['method'])->first();
            $measurement->method_id = $method->id;
        }

        if(isset($xmlResults['@attributes'])){
            if(isset($xmlResults['@attributes']['unit'])){
                $unit = ResultUnit::where('code', '=', $xmlResults['@attributes']['unit'])->first();
                $measurement->result_unit_id = $unit->id;
            }
            if(isset($xmlResults['@attributes']['fresh'])){
                $fresh = $xmlResults['@attributes']['fresh']==='true' ? 1 : 0;
                $measurement->resultsFresh = $fresh;
            }
        }

        if(isset($xmlMeasurement['ref-date'])){
            $measurement->referenceDate = $xmlMeasurement['ref-date'];
        }
        if(isset($xmlMeasurement['number'])){
            $measurement->number = $xmlMeasurement['number'];
        }

        $measurement->save();


        if(isset($xmlResults['result'][0])){
            foreach($xmlResults['result'] as $xmlResult) {
                $res = $this->manageResult($xmlResult, $measurement);
            }
        } else {
            $xmlResult = $xmlResults['result'];
            $res = $this->manageResult($xmlResult, $measurement);
        }

        return $res;
    }

    public function manageResult($xmlResult, $measurement)
    {
        $nuclide = Nuclide::where('code', '=', $xmlResult['nuclide'])->first();

        $result = new Result();
        $result->measurement_id = $measurement->id;
        $result->nuclide_id = $nuclide->id;

        if(isset($xmlResult['value'])) {
            $result->value = $xmlResult['value'];
        }
        $result->limited = isset($xmlResult['@attributes']['limit']) ? 1 : 0;
        if(isset($xmlResult['error'])) {
            $result->error = $xmlResult['error'];
        }

        $result->save();
        return true;
    }

    public function purgeDir()
    {
        collect(Storage::disk('lims')->listContents('processed', true))
            ->each(function($file) {
                if($file['type']=='file' &&
                    $file['lastModified'] < now()->subDays(2)->getTimestamp()) {
                        Storage::disk('lims')->delete($file['path']);
                    }
            });
    }
}
