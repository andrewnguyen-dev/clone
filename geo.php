<?php
	function geoPointInPolyArea($lat, $lon, $polyData) {
		list($y0, $x0) = mercatorProjection($lat, $lon);
		$points = explode('|', $polyData);
		$numPoints = floor(count($points) / 2);
		$points[] = $points[0];
		$points[] = $points[1];

		$numCrossing = 0;
		for ($i=0; $i<$numPoints; $i++) {
			list($y1, $x1) = mercatorProjection($points[$i * 2], $points[$i * 2 + 1]);
			list($y2, $x2) = mercatorProjection($points[$i * 2 + 2], $points[$i * 2 + 3]);
			if ($y1 > $y2) list($y1, $y2, $x1, $x2) = array($y2, $y1, $x2, $x1); // (x1, y1) is always the bottom point

			// if the points are above and below y0, and at least one of the x's is to the left...
			if ($y1 < $y0 && $y2 >= $y0 && ($x1 < $x0 || $x2 < $x0)) {
				$r = ($y0 - $y1) / ($y2 - $y1); // ratio of line that is below y0
				$x = $x1 + $r * ($x2 - $x1);
				if ($x == $x0) return true;
				elseif ($x < $x0) $numCrossing += 1;
			}
		}
		return ($numCrossing % 2 == 1);
	}

	function geoPointInDrivingDistance($lat, $lon, $details) {
		$details = explode("|", $details);
		$details = array_splice($details, 3);
		$details = implode("|", $details);
		return geoPointInPolyArea($lat, $lon, $details);
	}

	function geoPointInCircleArea($lat, $lon, $circleData) {
		list($lat2, $lon2, $radius) = explode('|', $circleData);
		return geoDistance($lat, $lon, $lat2, $lon2) <= $radius;
	}

	function mercatorProjection($lat, $lon) {
		$lat = log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat)));
		return array($lat, $lon);
	}

	function geoDistance($lat1, $lon1, $lat2, $lon2) {
		$R = 6371; // earth's mean radius in km
		$dLat = deg2rad($lat2-$lat1);
		$dLon = deg2rad($lon2-$lon1);
		$lat1 = deg2rad($lat1);
		$lat2 = deg2rad($lat2);
		$a = sin($dLat/2) * sin($dLat/2) + cos($lat1) * cos($lat2) * sin($dLon/2) * sin($dLon/2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
		return $R * $c;
	}
	function circleCircle($c1lon, $c1lat, $c1radius, $c2lon, $c2lat, $c2radius) {
		// if the distance between the two centers is less than the two radii the circles touch
		$distance = geoDistance($c1lat, $c1lon, $c2lat, $c2lon);
		if ($distance <= ($c1radius + $c2radius))
			return true;

		return false;
	}
	function lineLine($spreadCurrentLon, $spreadCurrentLat, $spreadNextLon, $spreadNextLat, $supCurrentLon, $supCurrentLat, $supNextLon, $supNextLat) {
		if((($supNextLat-$supCurrentLat)*($spreadNextLon-$spreadCurrentLon) - ($supNextLon-$supCurrentLon)*($spreadNextLat-$spreadCurrentLat)) == 0) {
			// current and next points have the same value. ie. line of 0 length.
			return false;
		}

		// calculate the direction of the lines
		$uA = (($supNextLon-$supCurrentLon)*($spreadCurrentLat-$supCurrentLat) - ($supNextLat-$supCurrentLat)*($spreadCurrentLon-$supCurrentLon)) / (($supNextLat-$supCurrentLat)*($spreadNextLon-$spreadCurrentLon) - ($supNextLon-$supCurrentLon)*($spreadNextLat-$spreadCurrentLat));
		$uB = (($spreadNextLon-$spreadCurrentLon)*($spreadCurrentLat-$supCurrentLat) - ($spreadNextLat-$spreadCurrentLat)*($spreadCurrentLon-$supCurrentLon)) / (($supNextLat-$supCurrentLat)*($spreadNextLon-$spreadCurrentLon) - ($supNextLon-$supCurrentLon)*($spreadNextLat-$spreadCurrentLat));

		// if uA and uB are between 0-1, lines are colliding
		if ($uA >= 0 && $uA <= 1 && $uB >= 0 && $uB <= 1) {
			return true;
		}
		return false;
	}
	function polyPoint($spreadPoly, $supPointLon, $supPointLat) {
		$collision = false;
		$countSpreadPoly = count($spreadPoly);
		for ($current=0; $current<$countSpreadPoly; $current++) {
			$next = $current + 1;
			if ($next == $countSpreadPoly) $next = 0;

			$vCurrent = $spreadPoly[$current];
			$vNext = $spreadPoly[$next];
		
			// compare position, flip 'collision' variable back and forth
			if ((($vCurrent['lat'] > $supPointLat && $vNext['lat'] < $supPointLat) || ($vCurrent['lat'] < $supPointLat && $vNext['lat'] > $supPointLat)) &&
				($supPointLon < ($vNext['lon'] - $vCurrent['lon']) * ($supPointLat - $vCurrent['lat']) / ($vNext['lat'] - $vCurrent['lat']) + $vCurrent['lon'])) {
				$collision = !$collision;
			}
		}
		return $collision;
	}
	function linePoint($lp1lon, $lp1lat, $lp2lon, $lp2lat, $plon, $plat) {
		// get distance from the point to the two ends of the line
		$d1 = geoDistance($plat, $plon, $lp1lat, $lp1lon);
		$d2 = geoDistance($plat, $plon, $lp2lat, $lp2lon);

		// get the length of the line
		$lineLen = geoDistance($lp1lat, $lp1lon, $lp2lat, $lp2lon);

		// Distance in km to allow a buffer
		$buffer = 0.5;    // higher # = less accurate

		// if the two distances are equal to the line's length, the point is on the line!
		// note we use the buffer here to give a range, rather than exact
		if ($d1 + $d2 >= $lineLen - $buffer && $d1 + $d2 <= $lineLen + $buffer)
			return true;

		return false;
	}
	function lineCircle($p1lon, $p1lat, $p2lon, $p2lat, $clon, $clat, $crad){
		// check if either point is within the circle
		$circleData = $clat . '|' . $clon . '|' . $crad;
		if(geoPointInCircleArea($p1lat, $p1lon, $circleData))
			return true;
		if(geoPointInCircleArea($p2lat, $p2lon, $circleData))
			return true;

		// get length of the line
		$distLon = $p1lon - $p2lon;
		$distLat = $p1lat - $p2lat;
		$length = sqrt(($distLon * $distLon) + ($distLat * $distLat));

		if($distLon == 0 && $distLat == 0){  // points on top of each other
			$closestLon = $distLon;
			$closestLat = $distLat;
		} else {
			// get dot product of the line and circle
			$dot = ((($clon - $p1lon) * ($p2lon - $p1lon)) + (($clat - $p1lat) * ($p2lat - $p1lat))) / pow($length,2);

			// find the closest point on the line
			$closestLon = $p1lon + ($dot * ($p2lon - $p1lon));
			$closestLat = $p1lat + ($dot * ($p2lat - $p1lat));
		}

		// Check if this point is on the line
		$onSegment = linePoint($p1lon, $p1lat, $p2lon, $p2lat, $closestLon,$closestLat);
		if (!$onSegment)
			return false;
		
		$distance = geoDistance($closestLat, $closestLon, $clat, $clon);

		// is the circle on the line?
		if ($distance <= $crad)
			return true;

		return false;
	}
	function polyCircle($polygon, $clon, $clat, $crad){
		// go through each point in the polygon + next point
		$countPoly = count($polygon);
		for ($current=0; $current<$countPoly; $current++) {
			$next = $current + 1;
			if ($next == $countPoly) $next = 0; // if we've reached the end of the shape use point 0 (enclosed shape)

			// check if line collides with circle
			$collision = lineCircle($polygon[$current]['lon'],$polygon[$current]['lat'], $polygon[$next]['lon'],$polygon[$next]['lat'], $clon,$clat,$crad);
			if ($collision)
				return true;
		}

		// The above checks if the edges cross, this checks if the circle is within the polyggon
		$centerInside = polyPoint($polygon, $clon,$clat);
		if ($centerInside)
			return true;

		return false;
	}
?>