<?php

namespace kernel\Modules\QCloud\STS;

/**
 * 腾讯云 STS 临时密钥申请（官方样例移植版）
 *
 * 向腾讯云 STS 申请临时密钥，供 COS 直传等场景使用；由 {@see QCloudSTS} 持有并调用
 * （它的 $stsInstance 属性就是本类实例）。
 *
 * 本文件源自腾讯云官方「临时密钥计算样例」，只做了命名空间/类名移植，**内部逻辑未重写**，
 * 官方样例的取舍一并保留（见下方「注意」）。
 *
 * 两条主线：
 * - {@see getTempKeys()}：GetFederationToken，**目前仓内实际使用的入口**（{@see QCloudSTS::getTempKeys()} 调用它）；
 * - {@see getRoleCredential()}：AssumeRole（需 roleArn），保留自样例，仓内暂未使用。
 *
 * 传入的 $config 常用键：
 * - 凭据：secretId / secretKey；
 * - 资源：bucket / region / allowPrefix（数组）/ allowActions（数组）/ condition（可选）；
 * - 其它：durationSeconds（整数，秒）、allowCiSource（为 true 时额外放行万象 ci 资源）；
 * - policy：直接给完整 policy，**跳过上面的自动组装**；
 * - roleArn / externalId：仅 {@see getRoleCredential()} 使用；
 * - url / domain / endpoint / proxy：覆盖服务地址与代理。
 *
 * 注意（实现现状，改动前先读）：
 * - 请求一律 CURLOPT_SSL_VERIFYPEER = 0 + CURLOPT_SSL_VERIFYHOST = 0（官方样例如此，**不校验 TLS 证书**）；
 * - 异常在 catch 里被**重新包装成 \Exception**（原始异常类型与消息会丢失，只剩文本）；
 * - durationSeconds 只做「存在则须为 int」的校验，**不是必填**；缺失时请求里的 DurationSeconds 为 null，由服务端报错；
 * - $ShortBucketName / $allow 等变量由官方样例保留，实际未被使用。
 *
 * @package kernel\Modules\QCloud\STS
 */
class QCloudSTSClient
{
	/**
	 * 十六进制字符串转二进制
	 *
	 * 等价于 PHP 5.4+ 的原生 hex2bin()，此处为官方样例自带的旧环境实现（签名计算用）。
	 *
	 * @param string $data 十六进制字符串（长度须为偶数）
	 * @return string 二进制数据
	 */
	function _hex2bin($data)
	{
		$len = strlen($data);
		return pack("H" . $len, $data);
	}
	/**
	 * 把参数数组拼成 query string（k=v&k=v 形式）
	 *
	 * 先按键名 ksort 排序，再按 $notEncode 决定值是否 rawurlencode ——
	 * 签名串必须用**不编码**的形式（$notEncode = true），而真正发出去的 POST body 用编码形式。
	 *
	 * 实现现状：ksort() 写在 is_array() 校验**之前**，所以传非数组时先触发一条 PHP 警告，
	 * 之后才抛出下面这个更友好的异常。
	 *
	 * @param array $obj 参数数组
	 * @param boolean $notEncode 是否**不做** URL 编码，默认 false
	 * @return string 拼接结果
	 * @throws \Exception $obj 不是数组时抛出
	 */
	function json2str($obj, $notEncode = false)
	{
		ksort($obj);
		$arr = array();
		if (!is_array($obj)) {
			throw new \Exception('$obj must be an array, the actual value is:' . json_encode($obj));
		}
		foreach ($obj as $key => $val) {
			array_push($arr, $key . '=' . ($notEncode ? $val : rawurlencode($val)));
		}
		return join('&', $arr);
	}
	/**
	 * 计算临时密钥请求的签名
	 *
	 * 拼串 = HTTP方法 + host + "/?" + 参数串(不编码) → hash_hmac("sha1", …)
	 * → _hex2bin() → base64_encode()。
	 *
	 * host 取值优先级：默认 sts.tencentcloudapi.com → $config 的 domain 键 → $config 的 endpoint 键
	 * （endpoint 会覆盖 domain）。
	 *
	 * @param array $opt 参与签名的参数（含 SecretId / Timestamp / Nonce / Action …）
	 * @param string $key 密钥 secretKey，用作 HMAC 的 key
	 * @param string $method HTTP 方法，本类固定传 POST
	 * @param array $config 配置（读取 domain / endpoint 键）
	 * @return string base64 形式的签名
	 */
	function getSignature($opt, $key, $method, $config)
	{
		$host = "sts.tencentcloudapi.com";

		if (array_key_exists('domain', $config)) {
			$host = $config['domain'];
		}

		if (array_key_exists('endpoint', $config)) {
			$host = "sts." . $config['endpoint'];
		}

		$formatString = $method . $host . '/?' . $this->json2str($opt, 1);
		$sign = hash_hmac('sha1', $formatString, $key);
		$sign = base64_encode($this->_hex2bin($sign));
		return $sign;
	}
	/**
	 * 兼容 v2 接口的返回键名：递归把每个键的首字母改成小写
	 *
	 * v2 时代返回键首字母小写、v3 改成大写，这里统一转回小写以向下兼容；
	 * 另外把 Token 特判映射为 sessionToken（临时密钥里的会话令牌）。
	 *
	 * @param array $result 接口返回的数组
	 * @return array 键名小写化后的数组
	 * @throws \Exception $result 不是数组时抛出
	 */
	function backwardCompat($result)
	{
		if (!is_array($result)) {
			throw new \Exception('$result must be an array, the actual value is:' . json_encode($result));
		}
		$compat = array();
		foreach ($result as $key => $value) {
			if (is_array($value)) {
				$compat[lcfirst($key)] = $this->backwardCompat($value);
			} elseif ($key == 'Token') {
				$compat['sessionToken'] = $value;
			} else {
				$compat[lcfirst($key)] = $value;
			}
		}
		return $compat;
	}
	/**
	 * 获取临时密钥（GetFederationToken）
	 *
	 * 流程：
	 * 1. 确定 policy —— 传了 $config 的 policy 键就直接用；否则由 bucket（解析出 AppId）
	 *    + region + allowPrefix + allowActions（可选 condition）组装 version 2.0 策略；
	 *    若 allowCiSource 为 true，额外附加一条万象 qcs::ci:…/bucket/{bucket}/* 资源；
	 * 2. 组装请求参数（SecretId / Timestamp / Nonce / DurationSeconds / Policy …）并计算 Signature；
	 * 3. curl POST 到 STS（地址可被 url / domain / endpoint 覆盖，proxy 可设代理）；
	 * 4. 解析返回：取 Response 节点，含 Error 则抛异常；再补一个 startTime
	 *    （= ExpiredTime - durationSeconds，即密钥生效时刻）；
	 * 5. 经 {@see backwardCompat()} 统一键名后返回。
	 *
	 * 失败时抛出的 \Exception 消息形如 "error: …"（各步骤的参数校验失败）或已获取到的响应
	 * JSON（get cam failed 等业务错误）。
	 *
	 * @param array $config 见类注释的 $config 常用键
	 * @return array 临时密钥（键名已小写化，含 credentials / expiredTime / startTime 等）
	 * @throws \Exception 参数缺失（bucket / allowPrefix / region）、durationSeconds 非整数、请求或业务失败
	 */
	function getTempKeys($config)
	{
		$result = null;
		try {
			if (array_key_exists('policy', $config)) {
				$policy = $config['policy'];
			} else {

				if (array_key_exists('bucket', $config)) {
					$ShortBucketName = substr($config['bucket'], 0, strripos($config['bucket'], '-'));
					$AppId = substr($config['bucket'], 1 + strripos($config['bucket'], '-'));
				} else {
					throw new \Exception("bucket== null");
				}

				if (array_key_exists('allowPrefix', $config)) {
					$resource = array();
					foreach ($config['allowPrefix'] as &$val) {
						if (!(strpos($val, '/') === 0)) {
							$allow = '/' . $val;
						}
						$resource[] = 'qcs::cos:' . $config['region'] . ':uid/' . $AppId . ':' . $config['bucket'] . '/' . $val;
					}
					// 处理万象资源
					if (array_key_exists('allowCiSource', $config) && $config['allowCiSource'] === true) {
						$resource[] = 'qcs::ci:' . $config['region'] . ':uid/' . $AppId . ':' . 'bucket/' . $config['bucket'] . '/*';
					}
				} else {
					throw new \Exception("allowPrefix == null");
				}


				if (!array_key_exists('region', $config)) {
					throw new \Exception("region == null");
				}

				if (!array_key_exists('condition', $config)) {
					$policy = array(
						'version' => '2.0',
						'statement' => array(
							array(
								'action' => $config['allowActions'],
								'effect' => 'allow',
								'resource' => $resource
							)
						)
					);
				} else {
					$policy = array(
						'version' => '2.0',
						'statement' => array(
							array(
								'action' => $config['allowActions'],
								'effect' => 'allow',
								'resource' => $resource,
								'condition' => $config['condition']
							)
						)
					);
				}
			}
			$policyStr = str_replace('\\/', '/', json_encode($policy));
			$Action = 'GetFederationToken';
			$Nonce = rand(10000, 20000);
			$Timestamp = time();
			$Method = 'POST';
			if (array_key_exists('durationSeconds', $config)) {
				if (!(is_integer($config['durationSeconds']))) {
					throw new \Exception("durationSeconds must be a int type");
				}
			}
			$params = array(
				'SecretId' => $config['secretId'],
				'Timestamp' => $Timestamp,
				'Nonce' => $Nonce,
				'Action' => $Action,
				'DurationSeconds' => $config['durationSeconds'],
				'Version' => '2018-08-13',
				'Name' => 'cos',
				'Region' => $config['region'],
				'Policy' => urlencode($policyStr)
			);
			$params['Signature'] = $this->getSignature($params, $config['secretKey'], $Method, $config);
			$url = 'https://sts.tencentcloudapi.com/';

			if (array_key_exists('url', $config)) {
				$url = $config['url'];
			}

			if (!array_key_exists('url', $config) && array_key_exists('domain', $config)) {
				$url = 'https://sts.' . $config['domain'];
			}

			if (array_key_exists('endpoint', $config)) {
				$url = 'https://sts.' . $config['endpoint'];
			}

			$ch = curl_init($url);
			if (array_key_exists('proxy', $config)) {
				$config['proxy'] && curl_setopt($ch, CURLOPT_PROXY, $config['proxy']);
			}
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->json2str($params));
			$result = curl_exec($ch);
			if (curl_errno($ch)) $result = curl_error($ch);
			curl_close($ch);
			$result = json_decode($result, 1);
			if (isset($result['Response'])) {
				$result = $result['Response'];
				if (isset($result['Error'])) {
					throw new \Exception("get cam failed");
				}
				$result['startTime'] = $result['ExpiredTime'] - $config['durationSeconds'];
			}
			$result = $this->backwardCompat($result);
			return $result;
		} catch (\Exception $e) {
			if ($result == null) {
				$result = "error: " . $e->getMessage();
			} else {
				$result = json_encode($result);
			}
			throw new \Exception($result);
		}
	}

	/**
	 * 获取临时密钥（无条件附加万象资源授权）
	 *
	 * @deprecated 官方已不再维护本方法 —— 请改用 {@see getTempKeys()} 并把 allowCiSource 设为 true。
	 *              本方法保留仅为兼容历史调用；与 getTempKeys() 的差别是**无条件**附加万象 ci 资源。
	 *
	 * @param array $config 同 {@see getTempKeys()}
	 * @return array 临时密钥
	 * @throws \Exception 同 {@see getTempKeys()}
	 */
	function getTempKeys4Ci($config)
	{
		$result = null;
		try {
			if (array_key_exists('policy', $config)) {
				$policy = $config['policy'];
			} else {

				if (array_key_exists('bucket', $config)) {
					$ShortBucketName = substr($config['bucket'], 0, strripos($config['bucket'], '-'));
					$AppId = substr($config['bucket'], 1 + strripos($config['bucket'], '-'));
				} else {
					throw new \Exception("bucket== null");
				}

				$resource = array();
				$resource[] = 'qcs::ci:' . $config['region'] . ':uid/' . $AppId . ':' . 'bucket/' . $config['bucket'] . '/*';
				if (array_key_exists('allowPrefix', $config)) {
					foreach ($config['allowPrefix'] as &$val) {
						if (!(strpos($val, '/') === 0)) {
							$allow = '/' . $val;
						}
						$resource[] = 'qcs::cos:' . $config['region'] . ':uid/' . $AppId . ':' . $config['bucket'] . '/' . $val;
					}
				} else {
					throw new \Exception("allowPrefix == null");
				}

				if (!array_key_exists('region', $config)) {
					throw new \Exception("region == null");
				}

				if (!array_key_exists('condition', $config)) {
					$policy = array(
						'version' => '2.0',
						'statement' => array(
							array(
								'action' => $config['allowActions'],
								'effect' => 'allow',
								'resource' => $resource
							)
						)
					);
				} else {
					$policy = array(
						'version' => '2.0',
						'statement' => array(
							array(
								'action' => $config['allowActions'],
								'effect' => 'allow',
								'resource' => $resource,
								'condition' => $config['condition']
							)
						)
					);
				}
			}
			$policyStr = str_replace('\\/', '/', json_encode($policy));
			$Action = 'GetFederationToken';
			$Nonce = rand(10000, 20000);
			$Timestamp = time();
			$Method = 'POST';
			if (array_key_exists('durationSeconds', $config)) {
				if (!(is_integer($config['durationSeconds']))) {
					throw new \Exception("durationSeconds must be a int type");
				}
			}
			$params = array(
				'SecretId' => $config['secretId'],
				'Timestamp' => $Timestamp,
				'Nonce' => $Nonce,
				'Action' => $Action,
				'DurationSeconds' => $config['durationSeconds'],
				'Version' => '2018-08-13',
				'Name' => 'cos',
				'Region' => $config['region'],
				'Policy' => urlencode($policyStr)
			);
			$params['Signature'] = $this->getSignature($params, $config['secretKey'], $Method, $config);
			$url = 'https://sts.tencentcloudapi.com/';

			if (array_key_exists('url', $config)) {
				$url = $config['url'];
			}

			if (!array_key_exists('url', $config) && array_key_exists('domain', $config)) {
				$url = 'https://sts.' . $config['domain'];
			}

			if (array_key_exists('endpoint', $config)) {
				$url = 'https://sts.' . $config['endpoint'];
			}

			$ch = curl_init($url);
			if (array_key_exists('proxy', $config)) {
				$config['proxy'] && curl_setopt($ch, CURLOPT_PROXY, $config['proxy']);
			}
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->json2str($params));
			$result = curl_exec($ch);
			if (curl_errno($ch)) $result = curl_error($ch);
			curl_close($ch);
			$result = json_decode($result, 1);
			if (isset($result['Response'])) {
				$result = $result['Response'];
				if (isset($result['Error'])) {
					throw new \Exception("get cam failed");
				}
				$result['startTime'] = $result['ExpiredTime'] - $config['durationSeconds'];
			}
			$result = $this->backwardCompat($result);
			return $result;
		} catch (\Exception $e) {
			if ($result == null) {
				$result = "error: " . $e->getMessage();
			} else {
				$result = json_encode($result);
			}
			throw new \Exception($result);
		}
	}

	/**
	 * 申请角色授权（AssumeRole）
	 *
	 * 与 {@see getTempKeys()} 同构，差别：
	 * - Action 为 AssumeRole，必须提供 $config 的 roleArn 键（否则抛异常），可选 externalId；
	 * - **默认服务地址是内网端点** https://sts.internal.tencentcloudapi.com/ ，非腾讯云内网
	 *   环境必须用 $config 的 endpoint 键覆盖（本方法**不认** url / domain 键）。
	 *
	 * @param array $config 见类注释的 $config 常用键（需 roleArn）
	 * @return array 角色临时凭据（键名已小写化）
	 * @throws \Exception 参数缺失（bucket / allowPrefix / region / roleArn）、durationSeconds 非整数、请求或业务失败
	 */
	function getRoleCredential($config)
	{
		$result = null;
		try {
			if (array_key_exists('policy', $config)) {
				$policy = $config['policy'];
			} else {
				if (array_key_exists('bucket', $config)) {
					$ShortBucketName = substr($config['bucket'], 0, strripos($config['bucket'], '-'));
					$AppId = substr($config['bucket'], 1 + strripos($config['bucket'], '-'));
				} else {
					throw new \Exception("bucket== null");
				}
				if (array_key_exists('allowPrefix', $config)) {
					$resource = array();
					foreach ($config['allowPrefix'] as &$val) {
						if (!(strpos($val, '/') === 0)) {
							$allow = '/' . $val;
						}
						$resource[] = 'qcs::cos:' . $config['region'] . ':uid/' . $AppId . ':' . $config['bucket'] . '/' . $val;
					}
				} else {
					throw new \Exception("allowPrefix == null");
				}
				if (!array_key_exists('region', $config)) {
					throw new \Exception("region == null");
				}
				if (!array_key_exists('condition', $config)) {
					$policy = array(
						'version' => '2.0',
						'statement' => array(
							array(
								'action' => $config['allowActions'],
								'effect' => 'allow',
								'resource' => $resource
							)
						)
					);
				} else {
					$policy = array(
						'version' => '2.0',
						'statement' => array(
							array(
								'action' => $config['allowActions'],
								'effect' => 'allow',
								'resource' => $resource,
								'condition' => $config['condition']
							)
						)
					);
				}
			}
			if (array_key_exists('roleArn', $config)) {
				$RoleArn = $config['roleArn'];
			} else {
				throw new \Exception("roleArn == null");
			}
			$policyStr = str_replace('\\/', '/', json_encode($policy));
			$Action = 'AssumeRole';
			$Nonce = rand(10000, 20000);
			$Timestamp = time();
			$Method = 'POST';
			$ExternalId = "";
			if (array_key_exists('externalId', $config)) {
				$ExternalId = $config['externalId'];
			}
			if (array_key_exists('durationSeconds', $config)) {
				if (!(is_integer($config['durationSeconds']))) {
					throw new \Exception("durationSeconds must be a int type");
				}
			}
			$params = array(
				'SecretId' => $config['secretId'],
				'Timestamp' => $Timestamp,
				'RoleArn' => $RoleArn,
				'Action' => $Action,
				'Nonce' => $Nonce,
				'DurationSeconds' => $config['durationSeconds'],
				'Version' => '2018-08-13',
				'RoleSessionName' => 'cos',
				'Region' => $config['region'],
				'ExternalId' => $ExternalId,
				'Policy' => urlencode($policyStr)
			);
			$params['Signature'] = $this->getSignature($params, $config['secretKey'], $Method, $config);
			$url = 'https://sts.internal.tencentcloudapi.com/';

			if (array_key_exists('endpoint', $config)) {
				$url = 'https://sts.' . $config['endpoint'];
			}
			$ch = curl_init($url);
			if (array_key_exists('proxy', $config)) {
				$config['proxy'] && curl_setopt($ch, CURLOPT_PROXY, $config['proxy']);
			}
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->json2str($params));
			$result = curl_exec($ch);
			if (curl_errno($ch)) $result = curl_error($ch);
			curl_close($ch);
			$result = json_decode($result, 1);
			if (isset($result['Response'])) {
				$result = $result['Response'];
				if (isset($result['Error'])) {
					throw new \Exception("get cam failed");
				}
				$result['startTime'] = $result['ExpiredTime'] - $config['durationSeconds'];
			}
			$result = $this->backwardCompat($result);
			return $result;
		} catch (\Exception $e) {
			if ($result == null) {
				$result = "error: " . $e->getMessage();
			} else {
				$result = json_encode($result);
			}
			throw new \Exception($result);
		}
	}


	/**
	 * 由 scope 对象数组组装 policy（version 2.0）
	 *
	 * 每个元素需是 {@see QCloudSTSScope}，逐一取其 get_action() / get_resource() / get_effect()
	 * 拼成一条 statement。注意：本方法**只判 $scopes 是否为数组**，元素类型不做校验，
	 * 传入非 Scope 对象会在调用其方法时致命。
	 *
	 * @param array $scopes {@see QCloudSTSScope} 数组
	 * @return array|null policy 数组；$scopes 不是数组时返回 null
	 */
	function getPolicy($scopes)
	{
		if (!is_array($scopes)) {
			return null;
		}
		$statements = array();

		for ($i = 0, $counts = count($scopes); $i < $counts; $i++) {
			$actions = array();
			$resources = array();
			array_push($actions, $scopes[$i]->get_action());
			array_push($resources, $scopes[$i]->get_resource());

			$statement = array(
				'action' => $actions,
				'effect' => $scopes[$i]->get_effect(),
				'resource' => $resources
			);
			array_push($statements, $statement);
		}

		$policy = array(
			'version' => '2.0',
			'statement' => $statements
		);
		return $policy;
	}
}
