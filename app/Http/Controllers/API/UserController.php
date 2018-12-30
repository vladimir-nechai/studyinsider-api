<?php

namespace App\Http\Controllers\API;

use App\Models\Course;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpFoundation\Response as ResponseCode;
use Tymon\JWTAuth\Facades\JWTAuth;


class UserController extends Controller
{

    /**
     * login with with Laravel Passport
     *
     * @deprecated Deprecated: no use in our application
     * @param Request $request
     * @return Response
     */
    public function loginLaravelPassport(Request $request){
        if(Auth::attempt(['email' => $request->get('email'), 'password' => $request->get('password')])){
            $user = Auth::user();
            $success['token'] =  $user->createToken('Evalooni angular')-> accessToken;
            return response()->json(['success' => $success], ResponseCode::HTTP_OK);
        }
        else{
            return response()->json(['error'=>'invalid credentials'], ResponseCode::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Register with Laravel Passport
     *
     * @deprecated Deprecated: no use in our application
     * @param Request $request
     * @return Response
     */
    public function registerLaravelPassport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed',
            'password_confirmation' => 'required|same:password',
        ]);
        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], ResponseCode::HTTP_BAD_REQUEST);
        }
        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        $user = User::create($input);
        $success['token'] =  $user->createToken('Evalooni angular')-> accessToken;
        $success['name'] =  $user->name;

        return response()->json(['success'=>$success], ResponseCode::HTTP_OK);
    }

    /**
     * User login with JWT
     *
     * @param Request $request
     * @return Response
     */
    public function loginJWT(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password'=> 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], ResponseCode::HTTP_BAD_REQUEST);
        }

        $credentials = $request->only('email', 'password');
        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'invalid credentials'], ResponseCode::HTTP_UNAUTHORIZED);
        }

        return response()->json(compact('token'), ResponseCode::HTTP_OK);
    }

    /**
     * Register User with JWT
     *
     * @param Request $request
     * @return Response
     */
    public function registerJWT(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users',
            'first_name' => 'required',
            'last_name' => 'required',
            'username' => 'required|unique:users',
            'gender' => 'required',
            'study_program_id' => 'required',
            'password'=> 'required|confirmed',
            'password_confirmation' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), ResponseCode::HTTP_BAD_REQUEST);
        }

        $input = $request->all();
        $input['password'] = bcrypt($input['password']);

        // default image
        if ($input['gender'] == 0) {
            $input['image'] = 'https://evalooni.de/assets/images/girl2.jpg';
        } else {
            $input['image'] = 'https://evalooni.de/assets/images/guy2.jpg';
        }

        $user = User::create($input);
        $token = JWTAuth::fromUser($user);

        return response()->json(compact('token'), ResponseCode::HTTP_OK);
    }

    /**
     * User profile data
     *
     * @return Response
     */
    public function profile()
    {
        try {
            $user = Auth::user();
            $user->load([
                'studyProgram',
                'bookmarks.topSkills',
                'bookmarks.topTags',
                'reviews.skills',
                'reviews.tags'
            ]);

            return response()->json($user, ResponseCode::HTTP_OK);

        } catch (UnauthorizedHttpException $exception) {

            return response()->json(['error' => 'user not found'], ResponseCode::HTTP_UNAUTHORIZED);

        }
    }

    /**
     * Edit User profile data
     *
     * @param Request $request user data to be edited
     * @return Response
     */
    public function edit(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'username' => 'required|unique:users',
            'gender' => 'required',
            'study_program_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), ResponseCode::HTTP_BAD_REQUEST);
        }

        // email and password shall not be edited here
        $input = $request->except(['email', 'password']);

        $user->update($input);

        $user->load([
            'studyProgram',
            'bookmarks.topSkills',
            'bookmarks.topTags',
            'reviews.skills',
            'reviews.tags'
        ]);
        return response()->json($user, ResponseCode::HTTP_ACCEPTED);
    }


    /**
     * Edit User password
     *
     * @param Request $request user data to be edited
     * @return Response
     */
    public function editPassword(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'password' => 'required|confirmed',
            'password_confirmation' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), ResponseCode::HTTP_BAD_REQUEST);
        }

        $password = bcrypt($request->input('password'));

        $user->password = $password;
        $user->save();

        return response()->json(['message' => 'password has been successfully changed'], ResponseCode::HTTP_ACCEPTED);

    }

    /**
     * TODO: fix, it does not work
     * user image upload
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadImage(Request $request) {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $name = str_slug($user->name, '_').'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/assets/images/profile-pictures');
            $imagePath = $destinationPath. "/".  $name;
            $image->move($destinationPath, $name);

            $user->image = 'https://evalooni.de/assets/images/profile-pictures/' . $name;
            $user->save();

            return response()->json(['message' => 'user image was successfully saved'], $this->successStatus);
        }
    }

    /**
     * Delete User profile
     *
     * @return Response
     */
    public function delete()
    {
        $user = Auth::user();

        $user->delete();

        return response()->json(['message' => 'user was successfully deleted'], ResponseCode::HTTP_NO_CONTENT);
    }

    /**
     * Adding bookmark for a specific semester
     *
     * @param integer $courseId
     * @param integer $semesterId
     * @return Response
     */
    public function addBookmark($courseId, $semesterId)
    {
        $user = Auth::user();

        try {
            $course = Course::findOrFail($courseId);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => 'Course with this ID does not exist'], ResponseCode::HTTP_BAD_REQUEST);
        }

        $bookmarkExists = $user->bookmarks()->where([
            'course_id' => $course->id,
            'semester_id' => $semesterId])
            ->exists();

        if ($bookmarkExists) {
            return response()->json(['message' => 'Course is saved already'], ResponseCode::HTTP_ALREADY_REPORTED);
        } else {
            $user->bookmarks()->save($course, ['semester_id' => $semesterId]);
            return response()->json(['message' => 'Course has been successfully saved'], ResponseCode::HTTP_CREATED);
        }
    }

    /**
     * Deletes bookmark for a given semester
     *
     * @param integer $courseId
     * @param integer $semesterId
     * @return Response
     */
    public function deleteBookmark($courseId, $semesterId)
    {
        $user = Auth::user();

        try {
            $course = Course::findOrFail($courseId);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => 'Course with this ID does not exist'], ResponseCode::HTTP_BAD_REQUEST);
        }

        $user->bookmarks()
            ->wherePivot('semester_id', $semesterId)
            ->detach($course->id, ['semester_id' => $semesterId]);

        return response()->json('', ResponseCode::HTTP_NO_CONTENT);
    }
}
