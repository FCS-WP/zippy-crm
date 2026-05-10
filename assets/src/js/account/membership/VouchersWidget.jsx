import { Card, CardContent } from "@/js/shared/ui/card.jsx";
import { number } from "@/js/shared/utils/format.js";

export function VouchersWidget({ vouchers, link }) {
	return (
		<Card>
			<CardContent className="zc-p-5">
				<div className="zc-flex zc-items-baseline zc-justify-between">
					<p className="zc-text-sm zc-text-zinc-500">Vouchers</p>
					{link ? (
						<a href={link} className="zc-text-xs zc-font-medium zc-text-zinc-700 hover:zc-text-zinc-900 hover:zc-underline">
							Browse →
						</a>
					) : null}
				</div>
				<dl className="zc-mt-3 zc-grid zc-grid-cols-2 zc-gap-3">
					<Stat label="Ready to use" value={number(vouchers.active)} accent />
					<Stat label="Redeemed"     value={number(vouchers.used)} />
				</dl>
			</CardContent>
		</Card>
	);
}

function Stat({ label, value, accent }) {
	return (
		<div className={`zc-rounded-lg zc-px-3 zc-py-2.5 ${accent ? "zc-bg-emerald-50" : "zc-bg-zinc-50"}`}>
			<dt className={`zc-text-xs ${accent ? "zc-text-emerald-700" : "zc-text-zinc-500"}`}>{label}</dt>
			<dd className={`zc-mt-0.5 zc-text-xl zc-font-semibold zc-tabular-nums ${accent ? "zc-text-emerald-900" : "zc-text-zinc-900"}`}>
				{value}
			</dd>
		</div>
	);
}
