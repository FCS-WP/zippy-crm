import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/js/shared/ui/card.jsx";
import { Badge } from "@/js/shared/ui/badge.jsx";
import { date } from "@/js/shared/utils/format.js";

const LEVEL_VARIANT = { free: "muted", silver: "silver", gold: "gold", vip: "vip" };
const STATUS_VARIANT = { active: "success", suspended: "danger", expired: "muted" };

export function LevelCard({ membership }) {
	const { level, level_label, multiplier, status, joined_at, expires_at, user } = membership;

	return (
		<Card>
			<CardHeader>
				<div className="zc-flex zc-items-start zc-justify-between zc-gap-4">
					<div>
						<CardDescription>Welcome back</CardDescription>
						<CardTitle>{user.display_name}</CardTitle>
					</div>
					<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-2">
						<Badge variant={LEVEL_VARIANT[level] ?? "muted"}>{level_label}</Badge>
						<Badge variant={STATUS_VARIANT[status] ?? "muted"}>{status}</Badge>
					</div>
				</div>
			</CardHeader>
			<CardContent>
				<dl className="zc-grid zc-grid-cols-2 zc-gap-4 zc-text-sm sm:zc-grid-cols-3">
					<Row label="Points multiplier" value={`${multiplier}×`} />
					<Row label="Member since"      value={date(joined_at)} />
					<Row label="Expires"           value={expires_at ? date(expires_at) : "Never"} />
				</dl>
			</CardContent>
		</Card>
	);
}

function Row({ label, value }) {
	return (
		<div>
			<dt className="zc-text-zinc-500">{label}</dt>
			<dd className="zc-mt-0.5 zc-font-medium zc-text-zinc-900">{value}</dd>
		</div>
	);
}
