<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyContactRequest;
use App\Mail\ContactReplyMail;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ContactController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Contact Index', only: ['index']),
            new Middleware('permission:Contact Show', only: ['show']),
            new Middleware('permission:Contact Reply', only: ['reply']),
            new Middleware('permission:Contact Delete', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the contacts.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Contact::with('repliedBy:id,name')->latest();

            // Filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            }

            $contacts = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Contacts retrieved successfully',
                'data' => $contacts
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve contacts',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified contact.
     */
    public function show($id)
    {
        try {
            $contact = Contact::with('repliedBy:id,name')->find($id);

            if (!$contact) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Contact not found'
                ], 404);
            }

            // Mark as seen if it's still pending
            if ($contact->status === 'pending') {
                $contact->update(['status' => 'seen']);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Contact retrieved successfully',
                'data' => $contact
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve contact',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Reply to a contact inquiry.
     */
    public function reply(ReplyContactRequest $request, $id)
    {
        try {
            $contact = Contact::find($id);

            if (!$contact) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Contact not found'
                ], 404);
            }

            DB::beginTransaction();

            $replyMessage = $request->reply_message;
            $admin = auth('api')->user();

            // Update contact record
            $contact->update([
                'reply_message' => $replyMessage,
                'replied_by' => $admin->id,
                'replied_at' => now(),
                'status' => 'replied',
                'is_replied' => true
            ]);

            // Send email
            try {
                Mail::to($contact->email)->send(new ContactReplyMail($contact, $replyMessage));
            } catch (\Exception $mailException) {
                \Log::error('Contact Reply Mail Error: ' . $mailException->getMessage());
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Reply sent and stored successfully',
                'data' => $contact->load('repliedBy:id,name')
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send reply',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $contact = Contact::find($id);

            if (!$contact) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cntact mail not found',
                    'data' => []
                ], 404);
            }

            $contact->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Contact mail deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete contact mail',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
