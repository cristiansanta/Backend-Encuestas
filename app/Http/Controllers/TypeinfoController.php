<?php

namespace App\Http\Controllers;
use App\Models\TypeinfoModel;


use Illuminate\Http\Request;

class TypeinfoController extends Controller
{
    public function getData(Request $request, $type)
    {
        try {
            $model = new TypeinfoModel();
            switch ($type) {
                case 'typecourses':
                    $data = $model->getTypecourse();
                    break;
                case 'ProgForma':
                    $data = $model->getProgForm();
                    break;
                case 'Regional':
                    $data = $model->getRegional();
                    break;
                case 'Usuarioslms':
                    $data = $model->getUsuarioslms();
                    break;
                case 'Fichas':
                    $data = $model->getFichas();
                    break;
                case 'Roles':
                    $data = $model->getroles($request);
                    break;
                case 'Tadist':
                    $data = $model->getModalidadT($request);
                    break;
                case 'virtual':
                    $data = $model->getModalidadV($request);
                    break;
                case 'presencial':
                    $data = $model->getModalidadP($request);
                    break;
                case 'alldata':
                    $data = $model->getall($request);
                    break;                                      
                default:
                    return response()->json(['error' => 'Invalid type'], 400);
            }
            return response()->json($data, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error retrieving data'], 500);
        }
    }
}