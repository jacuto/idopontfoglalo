<?php
namespace App\Services;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
class Invoices
{
    public function __construct(
        private SalonStore $store,
        private Connections $connections,
    ) {}
    public function issue(array $data): array
    {
        $s = $this->store->read();
        $provider = $s["settings"]["invoiceProvider"];
        $c = $this->connections->all();
        if (
            $provider === "none" ||
            ($provider === "billingo" &&
                (empty($c["billingo_key"]) || empty($c["billingo_block"]))) ||
            ($provider === "szamlazz" && empty($c["szamlazz_key"]))
        ) {
            throw new InvalidArgumentException("A számlázókapcsolat hiányos.");
        }
        $payment = collect($s["payments"])->firstWhere(
            "id",
            $data["paymentId"],
        );
        if (!$payment || $payment["status"] !== "paid") {
            throw new InvalidArgumentException("Nincs ilyen aktív befizetés.");
        }
        $booking = collect($s["bookings"])->firstWhere(
            "id",
            $payment["bookingId"],
        );
        if (
            $provider === "szamlazz" &&
            ($data["buyer"]["country"] ?? "HU") !== "HU"
        ) {
            throw new InvalidArgumentException(
                "A jelenlegi Számlázz.hu-kapcsolat magyarországi számlázási címeket támogat.",
            );
        }
        $invoiceAmount = collect($s["payments"])
            ->where("bookingId", $booking["id"])
            ->where("status", "paid")
            ->sum(fn($p) => $p["amount"] - ($p["refundedAmount"] ?? 0));
        if (
            abs(
                $invoiceAmount -
                    array_sum(
                        array_column($booking["serviceSnapshot"], "price"),
                    ),
            ) > 0.001
        ) {
            throw new InvalidArgumentException(
                "Számla csak a teljesen rendezett foglalásról készíthető; előlegszámla még nem támogatott.",
            );
        }
        $id = (string) Str::uuid();
        $this->store->mutate(function (&$s) use (
            $data,
            $provider,
            $id,
            $booking,
        ) {
            foreach ($s["invoiceRequests"] as $i) {
                if (
                    ($i["paymentId"] === $data["paymentId"] ||
                        ($i["bookingId"] ?? null) === $booking["id"]) &&
                    in_array(
                        $i["status"],
                        ["issuing", "issued", "unknown"],
                        true,
                    )
                ) {
                    throw new InvalidArgumentException(
                        "Ehhez a befizetéshez már készült vagy folyamatban van számla. Ellenőrizd a számlázónál.",
                    );
                }
            }
            $s["invoiceRequests"][] = [
                "id" => $id,
                "paymentId" => $data["paymentId"],
                "bookingId" => $booking["id"],
                "provider" => $provider,
                "status" => "issuing",
                "createdAt" => now()->toISOString(),
            ];
        });
        try {
            $buyer = $data["buyer"];
            $today = now()->format("Y-m-d");
            $vat = $s["settings"]["invoiceVat"];
            if ($provider === "billingo") {
                $partner = Http::timeout(20)
                    ->withHeaders(["X-API-KEY" => $c["billingo_key"]])
                    ->post("https://api.billingo.hu/v3/partners", [
                        "name" => $buyer["name"],
                        "address" => [
                            "country_code" => $buyer["country"],
                            "post_code" => $buyer["zip"],
                            "city" => $buyer["city"],
                            "address" => $buyer["address"],
                        ],
                        "emails" => empty($buyer["email"])
                            ? []
                            : [$buyer["email"]],
                        ...!empty($buyer["taxNumber"])
                            ? ["taxcode" => $buyer["taxNumber"]]
                            : [],
                    ])
                    ->throw()
                    ->json();
                $result = Http::timeout(30)
                    ->withHeaders(["X-API-KEY" => $c["billingo_key"]])
                    ->post("https://api.billingo.hu/v3/documents", [
                        "partner_id" => $partner["id"],
                        "block_id" => (int) $c["billingo_block"],
                        "type" => "invoice",
                        "fulfillment_date" => $today,
                        "due_date" => $today,
                        "payment_method" => [
                            "cash" => "cash",
                            "card" => "bankcard",
                            "transfer" => "wire_transfer",
                        ][$payment["method"]],
                        "language" => "hu",
                        "currency" => "HUF",
                        "conversion_rate" => 1,
                        "electronic" => true,
                        "paid" => true,
                        "items" => [
                            [
                                "name" =>
                                    "Szolgáltatás — " .
                                    implode(
                                        " + ",
                                        array_column(
                                            $booking["serviceSnapshot"],
                                            "name",
                                        ),
                                    ),
                                "unit_price" => $invoiceAmount,
                                "unit_price_type" => "gross",
                                "quantity" => 1,
                                "unit" => "db",
                                "vat" => $vat,
                            ],
                        ],
                        "comment" =>
                            "Foglalás: " .
                            $booking["id"] .
                            "; befizetés: " .
                            $payment["id"],
                    ])
                    ->throw()
                    ->json();
                $reference = (string) $result["id"];
                $number = $result["invoice_number"] ?? $reference;
            } else {
                $x = fn($v) => htmlspecialchars(
                    (string) $v,
                    ENT_XML1 | ENT_QUOTES,
                    "UTF-8",
                );
                $amount = $invoiceAmount;
                $percent = [
                    "AAM" => 0,
                    "27%" => 27,
                    "18%" => 18,
                    "5%" => 5,
                    "0%" => 0,
                ][$vat];
                $net = round($amount / (1 + $percent / 100), 2);
                $tax = round($amount - $net, 2);
                $xml =
                    '<?xml version="1.0" encoding="UTF-8"?><xmlszamla xmlns="http://www.szamlazz.hu/xmlszamla" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmlszamla https://www.szamlazz.hu/szamla/docs/xsds/agent.xsd"><beallitasok><szamlaagentkulcs>' .
                    $x($c["szamlazz_key"]) .
                    "</szamlaagentkulcs><eszamla>true</eszamla><szamlaLetoltes>false</szamlaLetoltes><valaszVerzio>2</valaszVerzio></beallitasok><fejlec><keltDatum>" .
                    $today .
                    "</keltDatum><teljesitesDatum>" .
                    $today .
                    "</teljesitesDatum><fizetesiHataridoDatum>" .
                    $today .
                    "</fizetesiHataridoDatum><fizmod>" .
                    $x(
                        [
                            "cash" => "készpénz",
                            "card" => "bankkártya",
                            "transfer" => "átutalás",
                        ][$payment["method"]],
                    ) .
                    "</fizmod><penznem>HUF</penznem><szamlaNyelve>hu</szamlaNyelve><megjegyzes>" .
                    $x("Befizetés: " . $payment["id"]) .
                    "</megjegyzes><fizetve>true</fizetve></fejlec><elado/><vevo><nev>" .
                    $x($buyer["name"]) .
                    "</nev><irsz>" .
                    $x($buyer["zip"]) .
                    "</irsz><telepules>" .
                    $x($buyer["city"]) .
                    "</telepules><cim>" .
                    $x($buyer["address"]) .
                    "</cim><email>" .
                    $x($buyer["email"] ?? "") .
                    "</email><sendEmail>false</sendEmail><adoszam>" .
                    $x($buyer["taxNumber"] ?? "") .
                    "</adoszam></vevo><tetelek><tetel><megnevezes>" .
                    $x(
                        "Szolgáltatás — " .
                            implode(
                                " + ",
                                array_column(
                                    $booking["serviceSnapshot"],
                                    "name",
                                ),
                            ),
                    ) .
                    "</megnevezes><mennyiseg>1</mennyiseg><mennyisegiEgyseg>db</mennyisegiEgyseg><nettoEgysegar>" .
                    $net .
                    "</nettoEgysegar><afakulcs>" .
                    $x($vat === "AAM" ? "AAM" : rtrim($vat, "%")) .
                    "</afakulcs><nettoErtek>" .
                    $net .
                    "</nettoErtek><afaErtek>" .
                    $tax .
                    "</afaErtek><bruttoErtek>" .
                    $amount .
                    "</bruttoErtek></tetel></tetelek></xmlszamla>";
                $response = Http::timeout(30)
                    ->attach("action-xmlagentxml", $xml, "invoice.xml")
                    ->post("https://www.szamlazz.hu/szamla/")
                    ->throw();
                if ($response->header("szlahu_error_code")) {
                    throw new \RuntimeException("Számlázási hiba");
                }
                $parsed = simplexml_load_string(
                    $response->body(),
                    \SimpleXMLElement::class,
                    LIBXML_NONET,
                );
                if (!$parsed || (string) $parsed->sikeres !== "true") {
                    throw new \RuntimeException("Számlázási hiba");
                }
                $reference = $number = (string) $parsed->szamlaszam;
            }
            $this->store->mutate(function (&$s) use ($id, $reference, $number) {
                foreach ($s["invoiceRequests"] as &$i) {
                    if ($i["id"] === $id) {
                        $i["status"] = "issued";
                        $i["reference"] = $reference;
                        $i["number"] = $number;
                    }
                }
            });
            return ["ok" => true, "number" => $number];
        } catch (\Throwable $e) {
            $this->store->mutate(function (&$s) use ($id) {
                foreach ($s["invoiceRequests"] as &$i) {
                    if ($i["id"] === $id) {
                        $i["status"] = "unknown";
                        $i["error"] =
                            "Ellenőrizd a számlázóban, létrejött-e a számla. Újraküldés előtt egyeztetés szükséges.";
                    }
                }
            });
            throw new InvalidArgumentException(
                "A számlázás eredménye nem igazolt. Ellenőrizd a számlázó felületén, mielőtt új számlát készítesz.",
            );
        }
    }
}
