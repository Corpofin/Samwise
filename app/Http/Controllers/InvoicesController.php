<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Http\Requests\Invoices\StoreRequest;
use App\Http\Requests\Invoices\UpdateRequest;
use App\Http\Requests\Invoices\StoreItemRequest;
use App\Http\Requests\Invoices\StorePaymentRequest;

use App\Search;
use App\Invoice;
use App\InvoiceItem;
use App\Payment;
use App\Item;
use JWTAuth;

use Stripe\Stripe;
use Stripe\Charge;

class InvoicesController extends Controller
{
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request, Search $search)
	{
		return $search->query('invoices', $request);
	}

	/**
	 * Display a listing of the deleted resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function indexCancelled(Request $request, Search $search)
	{
		return $search->query('cancelled-invoices', $request);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(StoreRequest $request)
	{
		$invoice = Invoice::create([
			'email' => $request->email,
			'phone' => $request->phone,
			'seperate_billing' => $request->seperate_billing,
			'billing_address' => $request->billing_address,
			'shipping_address' => $request->shipping_address
		]);

		$invoice_items = [];
		foreach ($request->cart as $offer_id => $items) {
			foreach ($items as $item_id => $count) {
				$item = Item::find($item_id);

				$invoice_items[] = new InvoiceItem([
					'item_id' => $item_id,
					'name' => $item->full_newline_name,
					'count' => $count,
					'price' => $item->price,
					'unit' => $item->unit
				]);
			}
		}

		$invoice->items()->saveMany($invoice_items);

		return $invoice;
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function show($id)
	{
		return Invoice::findOrFail($id);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(UpdateRequest $request, $id)
	{
		$allowed_fields = [
			'store_notes',
			'email',
			'phone',
			'seperate_billing',
			'billed',
			'paid',
			'shipped',
			'shipping_cost'
		];

		$invoice = Invoice::findOrFail($id);
		foreach ($request->all() as $key => $value) {
			if (!in_array($key, $allowed_fields)) {
				break;
			}
			$invoice[$key] = $value;
		}
		$invoice->save();
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id)
	{
		Invoice::withTrashed()->findOrFail($id)->forceDelete();
		sleep(1);
	}

	/**
	 * Soft delete specific resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function cancel($id)
	{
		Invoice::findOrFail($id)->delete();
		sleep(1);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function restore($id)
	{
		Invoice::withTrashed()->findOrFail($id)->restore();
		sleep(1);
	}

	public function indexItems()
	{

	}

	public function storeItem($id, StoreItemRequest $request)
	{

	}

	public function indexPayments()
	{

	}

	public function storePayment($id, StorePaymentRequest $request)
	{
		Stripe::setApiKey(env('STRIPE_SECRET'));

		$invoice = Invoice::findOrFail($id);
		$token = $request->token;

		if ($request->amount != $invoice->due) {
			return 'hererrere';
			// THROW ERROR
			// AMT User thought they were paying has changed
			// OR something more seedy is going on with them
		}

		try
		{
			$charge = Charge::create([
				'amount' => $invoice->due,
				'currency' => 'usd',
				'description' => "INV$id",
				'card' => $token['id']
			]);

			$user = JWTAuth::parseToken()->authenticate();

			$payment = [
				'user_id' => $user->id,
				'stripe_id' => $charge['id'],
				'amount' => $charge['amount'],
				'currency' => $charge['currency'],
				'card_brand' => $charge['source']['brand'],
				'last_four' => $charge['source']['last4']
			];

			$payment = $invoice->payments()->save(new Payment($payment));
			$payment['user'] = $payment->user->name;

			return $payment;

		} catch(\Stripe\Error\Card $e) {

			return response()->json(['Card Declined' => [$e->getStripeCode()]], 422);

		} catch (Stripe_InvalidRequestError $e) {
			// Invalid parameters were supplied to Stripe's API
			return response()->json('Stripe_InvalidRequestError.', 422);

		} catch (Stripe_AuthenticationError $e) {
			// Authentication with Stripe's API failed
			// (maybe you changed API keys recently)
			return response()->json('Stripe_AuthenticationError.', 422);

		} catch (Stripe_ApiConnectionError $e) {
			// Network communication with Stripe failed
			return response()->json('Stripe_ApiConnectionError.', 422);

		} catch (Stripe_Error $e) {
			// Display a very generic error to the user, and maybe send
			// yourself an email
			return response()->json('Stripe_Error.', 422);

		} catch (Exception $e) {
			// Something else happened, completely unrelated to Stripe
			return response()->json('Exception.', 422);

		}
	}
}
